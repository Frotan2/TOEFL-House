<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Privacy\Commands\DefineConsentPurpose;
use App\Modules\Privacy\Commands\ExportSubjectData;
use App\Modules\Privacy\Commands\RecordConsent;
use App\Modules\Privacy\Commands\RecordDisclosure;
use App\Modules\Privacy\Commands\TransitionConsent;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Models\PrivacyExportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Legacy write transport kept as a thin compatibility adapter; the canonical
 * privacy surface is the React workspace on `/api/v1/privacy`. Mutation
 * commands remain the authoritative write path.
 */
final class PrivacyController extends Controller
{
    public function definePurpose(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
        ]);

        app(DefineConsentPurpose::class)->define($this->actor(), $input['name'], $input['channel'], $input['category'], $this->idempotencyKey('privacy.purpose.define'));

        return redirect()->route('privacy.index')->with('success', 'Consent purpose defined.');
    }

    public function recordConsent(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string'],
            'purpose_id' => ['required', 'string'],
            'evidence_ref' => ['required', 'string', 'max:500'],
            'effective_from' => ['required', 'date'],
            // Both transports agree with `RecordConsent` and the
            // consents_period_check boundary: a window must end *after* it
            // starts, so an equal-date request is a 422 here rather than a
            // domain rejection deeper in the stack.
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        app(RecordConsent::class)->record($this->actor(), $input['subject_person_id'], $input['purpose_id'], $input['evidence_ref'], CarbonImmutable::parse($input['effective_from']), (($input['effective_to'] ?? '') !== '') ? CarbonImmutable::parse($input['effective_to']) : null, $this->idempotencyKey('privacy.consent.record'));

        return redirect()->route('privacy.index')->with('success', 'Consent recorded as a draft with its evidence; it takes effect once verified and activated.');
    }

    public function submitConsent(Request $request, string $consentId): RedirectResponse
    {
        app(TransitionConsent::class)->submit($this->actor(), Consent::query()->findOrFail($consentId), $this->idempotencyKey('privacy.consent.submit'));

        return redirect()->route('privacy.index')->with('success', 'Consent submitted for verification.');
    }

    public function verifyConsent(Request $request, string $consentId): RedirectResponse
    {
        app(TransitionConsent::class)->verify($this->actor(), Consent::query()->findOrFail($consentId), $this->idempotencyKey('privacy.consent.verify'));

        return redirect()->route('privacy.index')->with('success', 'Consent verified against its evidence.');
    }

    public function activateConsent(Request $request, string $consentId): RedirectResponse
    {
        app(TransitionConsent::class)->activate($this->actor(), Consent::query()->findOrFail($consentId), $this->idempotencyKey('privacy.consent.activate'));

        return redirect()->route('privacy.index')->with('success', 'Consent active.');
    }

    public function expireConsent(Request $request, string $consentId): RedirectResponse
    {
        app(TransitionConsent::class)->expire($this->actor(), Consent::query()->findOrFail($consentId), $this->idempotencyKey('privacy.consent.expire'));

        return redirect()->route('privacy.index')->with('success', 'Consent expired; the record and its evidence are retained.');
    }

    public function revokeConsent(Request $request, string $consentId): RedirectResponse
    {
        $input = $request->validate([
            'scope' => ['required', 'string', 'max:200'],
            'effect' => ['required', 'string', 'max:200'],
        ]);

        app(TransitionConsent::class)->revoke($this->actor(), Consent::query()->findOrFail($consentId), $input['scope'], $input['effect'], $this->idempotencyKey('privacy.consent.revoke'));

        return redirect()->route('privacy.index')->with('success', 'Consent revoked with its scope and effect recorded.');
    }

    public function archiveConsent(Request $request, string $consentId): RedirectResponse
    {
        app(TransitionConsent::class)->archive($this->actor(), Consent::query()->findOrFail($consentId), $this->idempotencyKey('privacy.consent.archive'));

        return redirect()->route('privacy.index')->with('success', 'Consent archived; the history is retained.');
    }

    public function recordDisclosure(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string'],
            'recipient' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:500'],
            'authority' => ['required', 'string', 'max:120'],
            'scope_type' => ['required', 'in:organization,campus,branch,department,subject'],
            'scope_id' => ['required', 'string'],
            'disclosed_category' => ['required', 'string', 'max:200'],
        ]);

        app(RecordDisclosure::class)->disclose($this->actor(), $input['subject_person_id'], $input['recipient'], $input['purpose'], $input['authority'], $input['scope_type'], $input['scope_id'], $input['disclosed_category'], $this->idempotencyKey('privacy.disclose'));

        return redirect()->route('privacy.index')->with('success', 'Disclosure recorded as immutable release evidence.');
    }

    public function directExport(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string'],
            'purpose' => ['required', 'string', 'max:500'],
            'scope_type' => ['required', 'in:campus,branch,department,subject'],
            'scope_id' => ['required', 'string'],
        ]);

        app(ExportSubjectData::class)->export($this->actor(), $input['subject_person_id'], $input['purpose'], $input['scope_type'], $input['scope_id'], $this->idempotencyKey('privacy.export'));

        return redirect()->route('privacy.index')->with('success', 'Subject data exported; the disclosure is recorded as the evidence of the release.');
    }

    public function requestExport(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string'],
            'purpose' => ['required', 'string', 'max:500'],
            'organization_id' => ['required', 'string'],
        ]);

        app(ExportSubjectData::class)->request($this->actor(), $input['subject_person_id'], $input['purpose'], $input['organization_id'], $this->idempotencyKey('privacy.export.request'));

        return redirect()->route('privacy.index')->with('success', 'Organization-wide export requested; it executes only after two distinct approvers sign in their own sessions.');
    }

    public function approveExport(Request $request, string $requestId): RedirectResponse
    {
        app(ExportSubjectData::class)->approve($this->actor(), PrivacyExportRequest::query()->findOrFail($requestId), $this->idempotencyKey('privacy.export.approve'));

        return redirect()->route('privacy.index')->with('success', 'Approval signed; the export executes once a distinct second approver signs.');
    }

    public function executeExport(Request $request, string $requestId): RedirectResponse
    {
        app(ExportSubjectData::class)->execute($this->actor(), PrivacyExportRequest::query()->findOrFail($requestId), $this->idempotencyKey('privacy.export.execute'));

        return redirect()->route('privacy.index')->with('success', 'Export executed; the disclosure is recorded as the evidence of the release.');
    }
}
