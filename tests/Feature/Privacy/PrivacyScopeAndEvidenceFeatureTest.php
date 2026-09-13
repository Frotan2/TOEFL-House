<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Modules\Privacy\Commands\DefineConsentPurpose;
use App\Modules\Privacy\Commands\RecordConsent;
use App\Modules\Privacy\Commands\RecordDisclosure;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Models\ConsentRevocation;
use App\Modules\Privacy\Models\Disclosure;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class PrivacyScopeAndEvidenceFeatureTest extends TestCase
{
    use BuildsActors;

    public function test_declared_disclosure_scope_cannot_override_subject_provenance(): void
    {
        $actor = $this->privacyOfficer('privacy-scope-actor');
        $subject = $this->personWithAuthority('privacy-scope-subject', []);

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('privacy scope');

        app(RecordDisclosure::class)->disclose(
            $actor,
            $subject->id,
            'recipient',
            'purpose',
            'privacy.disclose',
            'branch',
            '00000000-0000-4000-8000-00000000ffff',
            'identity',
            'privacy-scope-regression-1',
        );
    }

    public function test_privacy_evidence_has_database_append_only_triggers(): void
    {
        $triggerNames = collect(DB::select(
            "SELECT tgname FROM pg_trigger WHERE tgrelid IN ('disclosures'::regclass, 'consent_revocations'::regclass, 'consents'::regclass, 'privacy_export_requests'::regclass) AND NOT tgisinternal"
        ))->pluck('tgname')->all();

        $this->assertContains('disclosures_append_only_trigger', $triggerNames);
        $this->assertContains('consent_revocations_append_only_trigger', $triggerNames);
        // Consent facts and the staged export chain are guarded at the
        // database boundary too, so a writer that bypasses both the command
        // and the model still cannot forge or rewrite privacy evidence.
        $this->assertContains('consents_guard_trigger', $triggerNames);
        $this->assertContains('privacy_export_requests_guard_trigger', $triggerNames);
    }

    public function test_consent_facts_cannot_be_rewritten_or_erased_through_the_model(): void
    {
        $officer = $this->privacyOfficer('privacy-model-officer');
        $subject = $this->personWithAuthority('privacy-model-subject', []);
        $purposeId = app(DefineConsentPurpose::class)
            ->define($officer, 'model-boundary-purpose', 'communication', 'outreach', 'privacy-model-purpose-1')['purpose_id'];
        $recorded = app(RecordConsent::class)
            ->record($officer, $subject->id, $purposeId, 'evidence/model-boundary', new CarbonImmutable('2026-08-01'), null, 'privacy-model-consent-1');

        /** @var Consent $consent */
        $consent = Consent::query()->findOrFail($recorded['consent_id']);

        // A forged birth state and a rewritten evidence locator are domain
        // rejections, not database errors leaking to the caller.
        $forged = new Consent(['id' => 'consent-forged', 'lifecycle_state' => 'active', 'evidence_ref' => 'evidence/forged']);
        try {
            $forged->save();
            $this->fail('expected a forged active consent to be rejected');
        } catch (BusinessRejection $exception) {
            $this->assertSame('privacy.consent_state_forbidden', $exception->errorCode());
        }

        try {
            $consent->forceFill(['evidence_ref' => 'evidence/tampered'])->save();
            $this->fail('expected a consent evidence rewrite to be rejected');
        } catch (BusinessRejection $exception) {
            $this->assertSame('privacy.consent_immutable', $exception->errorCode());
        }

        try {
            $consent->refresh()->forceFill(['lifecycle_state' => 'archived'])->save();
            $this->fail('expected a skipped lifecycle step to be rejected');
        } catch (BusinessRejection $exception) {
            $this->assertSame('privacy.consent_transition_forbidden', $exception->errorCode());
        }

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('consent evidence is retained');
        $consent->refresh()->delete();
    }

    public function test_consent_evidence_survives_a_raw_sql_rewrite_attempt(): void
    {
        $officer = $this->privacyOfficer('privacy-raw-officer');
        $subject = $this->personWithAuthority('privacy-raw-subject', []);
        $purposeId = app(DefineConsentPurpose::class)
            ->define($officer, 'raw-boundary-purpose', 'communication', 'outreach', 'privacy-raw-purpose-1')['purpose_id'];
        $recorded = app(RecordConsent::class)
            ->record($officer, $subject->id, $purposeId, 'evidence/raw-boundary', new CarbonImmutable('2026-08-01'), null, 'privacy-raw-consent-1');

        // The trigger is the boundary for a writer that bypasses Eloquent
        // entirely: facts are write-once and consent evidence is never erased.
        $this->expectException(QueryException::class);
        DB::statement('UPDATE consents SET evidence_ref = ? WHERE id = ?', ['evidence/tampered', $recorded['consent_id']]);
    }

    public function test_disclosure_cannot_be_rewritten_or_deleted_through_model(): void
    {
        $disclosure = new Disclosure(['id' => 'disclosure-existing']);
        $disclosure->exists = true;

        try {
            $disclosure->save();
            $this->fail('expected disclosure mutation to be rejected');
        } catch (BusinessRejection $exception) {
            $this->assertSame('privacy.disclosure_immutable', $exception->errorCode());
        }

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('append-only');
        $disclosure->delete();
    }

    public function test_revocation_cannot_be_rewritten_or_deleted_through_model(): void
    {
        $revocation = new ConsentRevocation(['id' => 'revocation-existing']);
        $revocation->exists = true;

        try {
            $revocation->save();
            $this->fail('expected revocation mutation to be rejected');
        } catch (BusinessRejection $exception) {
            $this->assertSame('privacy.revocation_immutable', $exception->errorCode());
        }

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('append-only');
        $revocation->forceDelete();
    }
}
