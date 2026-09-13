<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Domain;

use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;

/**
 * Staged organization-wide export chain: requested -> approved -> exported.
 *
 * One authority owns the chain's legality so the command that signs it and
 * the projection that offers a signature cannot disagree: a request accepts
 * signatures only while it is `requested`, the same actor may never supply
 * both signatures, an approved request executes once, and an executed
 * request is closed. Capability, scope, subject provenance, idempotency and
 * audit decisions stay with `ExportSubjectData`; this registry decides only
 * which chain step is legal next.
 */
final class ExportApprovalChain
{
    public const STATE_REQUESTED = 'requested';

    public const STATE_APPROVED = 'approved';

    public const STATE_EXPORTED = 'exported';

    private const TRANSITIONS = [
        self::STATE_REQUESTED => [self::STATE_APPROVED],
        self::STATE_APPROVED => [self::STATE_EXPORTED],
        self::STATE_EXPORTED => [],
    ];

    /** @return list<string> */
    public static function states(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public static function allowsTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Whether this actor may add a signature now. It is false — never an
     * exception — for a projection that must simply not offer the control:
     * a closed request, or a request this actor already signed first.
     */
    public static function acceptsSignature(string $state, ?string $approverOneId, string $actorId): bool
    {
        if ($state !== self::STATE_REQUESTED) {
            return false;
        }

        return $approverOneId === null || self::identifier($approverOneId) !== self::identifier($actorId);
    }

    /**
     * Chain legality of one signature. The first signature fills the first
     * slot and leaves the request `requested`; a distinct second signature
     * closes it as `approved`.
     *
     * @return self::STATE_REQUESTED|self::STATE_APPROVED
     */
    public static function requireSignature(string $state, ?string $approverOneId, string $actorId): string
    {
        if ($state !== self::STATE_REQUESTED) {
            throw BusinessRejection::forCode(
                'privacy.export_request_state',
                sprintf('the request is already %s; approvals only count while it is requested', $state),
            );
        }

        if ($approverOneId !== null && self::identifier($approverOneId) === self::identifier($actorId)) {
            throw AuthorizationDenied::forCode(
                'privacy.bulk_export_single_actor',
                'organization-wide exports require two distinct approvers',
            );
        }

        return $approverOneId === null ? self::STATE_REQUESTED : self::STATE_APPROVED;
    }

    public static function allowsExecution(string $state): bool
    {
        return $state === self::STATE_APPROVED;
    }

    public static function requireExecution(string $state): void
    {
        if (! self::allowsExecution($state)) {
            throw BusinessRejection::forCode(
                'privacy.export_request_state',
                sprintf('the request must be approved before execution; it is %s', $state),
            );
        }
    }

    public static function isClosed(string $state): bool
    {
        return $state === self::STATE_EXPORTED;
    }

    private static function identifier(mixed $value): string
    {
        return trim((string) $value);
    }
}
