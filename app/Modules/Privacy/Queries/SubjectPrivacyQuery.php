<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Queries;

use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Privacy\Domain\ExportApprovalChain;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Models\ConsentRevocation;
use App\Modules\Privacy\Models\Disclosure;
use App\Modules\Privacy\Models\PrivacyExportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only privacy dossier of one subject as of a day.
 *
 * `consents` answers the only operational question — which purposes carry
 * current use authority right now (revoked, expired, archived and lapsed
 * consent never counts). `consent_history` keeps the complete record with its
 * withdrawal evidence so a subject access request can be answered without a
 * second read model, and `disclosures`/`export_requests` are the release
 * evidence. No query result is an authority to mutate.
 *
 * Consent evidence locators (`evidence_ref`) are write-only across every read
 * model, exactly like document storage references: they are accepted by the
 * recording command and never projected, so a broad list cannot become a
 * retrieval map for consent artifacts.
 */
final class SubjectPrivacyQuery
{
    /**
     * @return array{
     *     subject_person_id: string,
     *     as_of: string,
     *     consents: list<array<string, mixed>>,
     *     consent_history: list<array<string, mixed>>,
     *     disclosures: list<array<string, mixed>>,
     *     export_requests: list<array<string, mixed>>
     * }
     */
    public function subjectProfile(string $subjectPersonId, ?CarbonImmutable $asOf = null): array
    {
        $day = ($asOf ?? app(CalendarAuthority::class)->nowUtc())->startOfDay()->toDateString();

        /** @var Collection<int, Consent> $recorded */
        $recorded = Consent::query()
            ->where('subject_person_id', $subjectPersonId)
            ->orderBy('effective_from')
            ->get(['id', 'purpose_id', 'lifecycle_state', 'effective_from', 'effective_to', 'recorded_by']);

        $revocations = ConsentRevocation::query()
            ->whereIn('consent_id', $recorded->pluck('id')->all())
            ->orderBy('created_at')
            ->get(['id', 'consent_id', 'revoked_by', 'scope', 'effect', 'created_at'])
            ->groupBy(static fn (ConsentRevocation $revocation): string => self::identifier($revocation->consent_id));

        $consentRow = static function (Consent $consent) use ($revocations): array {
            $consentId = self::identifier($consent->id);
            /** @var list<array<string, mixed>> $withdrawals */
            $withdrawals = ($revocations[$consentId] ?? collect())
                ->map(static fn (ConsentRevocation $revocation): array => [
                    'revocation_id' => self::identifier($revocation->id),
                    'revoked_by' => self::identifier($revocation->revoked_by),
                    'scope' => (string) $revocation->scope,
                    'effect' => (string) $revocation->effect,
                    'at' => $revocation->created_at?->toDateTimeString(),
                ])->values()->all();

            return [
                'consent_id' => $consentId,
                'purpose_id' => self::identifier($consent->purpose_id),
                'lifecycle_state' => (string) $consent->lifecycle_state,
                'effective_from' => self::day($consent->effective_from),
                'effective_to' => $consent->effective_to === null ? null : self::day($consent->effective_to),
                'recorded_by' => self::identifier($consent->recorded_by),
                'revocations' => $withdrawals,
            ];
        };

        $consents = array_values($recorded
            ->filter(static fn (Consent $consent): bool => self::coversDay($consent, $day))
            ->map($consentRow)
            ->all());

        $disclosures = array_values(Disclosure::query()
            ->where('subject_person_id', $subjectPersonId)
            ->orderBy('created_at')
            ->get(['id', 'recipient', 'purpose', 'authority', 'scope_type', 'scope_id', 'disclosed_category', 'disclosed_by', 'created_at'])
            ->map(static fn (Disclosure $disclosure): array => [
                'disclosure_id' => self::identifier($disclosure->id),
                'recipient' => (string) $disclosure->recipient,
                'purpose' => (string) $disclosure->purpose,
                'authority' => (string) $disclosure->authority,
                'scope' => $disclosure->scope_type.':'.self::identifier($disclosure->scope_id),
                'disclosed_category' => (string) $disclosure->disclosed_category,
                'disclosed_by' => self::identifier($disclosure->disclosed_by),
                'at' => $disclosure->created_at?->toDateTimeString(),
            ])
            ->all());

        $exportRequests = array_values(PrivacyExportRequest::query()
            ->where('subject_person_id', $subjectPersonId)
            ->orderBy('created_at')
            ->get(['id', 'purpose', 'organization_id', 'lifecycle_state', 'requested_by', 'approver_one_id', 'approver_two_id', 'exported_by', 'disclosure_id', 'created_at'])
            ->map(static fn (PrivacyExportRequest $request): array => [
                'request_id' => self::identifier($request->id),
                'purpose' => (string) $request->purpose,
                'organization_id' => self::identifier($request->organization_id),
                'lifecycle_state' => (string) $request->lifecycle_state,
                'requested_by' => self::identifier($request->requested_by),
                'approver_one_id' => $request->approver_one_id === null ? null : self::identifier($request->approver_one_id),
                'approver_two_id' => $request->approver_two_id === null ? null : self::identifier($request->approver_two_id),
                'exported_by' => $request->exported_by === null ? null : self::identifier($request->exported_by),
                'disclosure_id' => $request->disclosure_id === null ? null : self::identifier($request->disclosure_id),
                // Projected from the same chain registry the command signs
                // against, so a reader sees exactly one closed/open truth.
                'closed' => ExportApprovalChain::isClosed((string) $request->lifecycle_state),
                'at' => $request->created_at?->toDateTimeString(),
            ])
            ->all());

        return [
            'subject_person_id' => $subjectPersonId,
            'as_of' => $day,
            'consents' => $consents,
            'consent_history' => array_values($recorded->map($consentRow)->all()),
            'disclosures' => $disclosures,
            'export_requests' => $exportRequests,
        ];
    }

    /** Current use authority: active lifecycle state inside its window. */
    private static function coversDay(Consent $consent, string $day): bool
    {
        if ((string) $consent->lifecycle_state !== 'active') {
            return false;
        }
        if (self::day($consent->effective_from) > $day) {
            return false;
        }

        return $consent->effective_to === null || self::day($consent->effective_to) > $day;
    }

    private static function day(mixed $value): string
    {
        return substr(trim((string) $value), 0, 10);
    }

    private static function identifier(mixed $value): string
    {
        return trim((string) $value);
    }
}
