<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Modules\Privacy\Commands\RecordDisclosure;
use App\Modules\Privacy\Models\ConsentRevocation;
use App\Modules\Privacy\Models\Disclosure;
use App\Support\Errors\BusinessRejection;
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
            "SELECT tgname FROM pg_trigger WHERE tgrelid IN ('disclosures'::regclass, 'consent_revocations'::regclass) AND NOT tgisinternal"
        ))->pluck('tgname')->all();

        $this->assertContains('disclosures_append_only_trigger', $triggerNames);
        $this->assertContains('consent_revocations_append_only_trigger', $triggerNames);
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
