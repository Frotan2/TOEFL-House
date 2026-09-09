<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Modules\Privacy\Commands\RecordDisclosure;
use App\Modules\Privacy\Models\ConsentRevocation;
use App\Modules\Privacy\Models\Disclosure;
use App\Support\Errors\BusinessRejection;
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

    public function test_disclosure_and_revocation_evidence_are_model_immutable(): void
    {
        $disclosure = new Disclosure(['id' => 'disclosure-existing']);
        $disclosure->exists = true;
        $this->expectException(BusinessRejection::class);
        $disclosure->save();
    }

    public function test_revocation_evidence_cannot_be_rewritten(): void
    {
        $revocation = new ConsentRevocation(['id' => 'revocation-existing']);
        $revocation->exists = true;
        $this->expectException(BusinessRejection::class);
        $revocation->save();
    }
}
