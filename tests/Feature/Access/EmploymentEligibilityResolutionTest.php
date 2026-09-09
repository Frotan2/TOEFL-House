<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsAuthority;
use Tests\TestCase;

/**
 * HR eligibility is part of authorization, and until now nothing in the access
 * suite exercised it: `grep -rn employment tests/Feature/Access` was empty, while
 * `AccessResolution` consults eligibility at four points per decision and denies
 * authority at each. A gate that no test crosses is a gate that can be removed by
 * refactor and stay green, so the behaviour cases below come first and the cost
 * cases after — the memo in `AccessResolution` must not become a way to skip the
 * check.
 *
 * The rule under test, from the class doc: a person with no employment relationship
 * may still hold authority (an explicitly appointed owner or auditor), but once HR
 * owns *any* employment row for them, only the newest one being active keeps that
 * authority. The newest row wins rather than the row effective today, which is why
 * the fixtures below set `created_at` explicitly instead of relying on insertion
 * order.
 */
final class EmploymentEligibilityResolutionTest extends TestCase
{
    use SeedsAuthority;

    private const CAPABILITY = 'reporting.audit';

    public function test_authority_without_an_employment_relationship_is_allowed_and_checked_once(): void
    {
        $personId = $this->personWithAuthority('elig-none', [self::CAPABILITY])->id;

        $decision = app(AccessDecision::class)->decide(new Actor(trim($personId), 'Eligibility Fixture'), self::CAPABILITY, null);

        $this->assertTrue($decision->allowed, 'a person with no HR relationship must not be denied by an eligibility check that has nothing to say about them');

        // Four consult sites, one query: the exists() probe finds nothing and every
        // later consult takes the memo. Before the memo this was eight.
        $this->assertSame(1, $this->employmentQueryCount(function () use ($personId): void {
            app(AccessDecision::class)->decide(new Actor(trim($personId), 'Eligibility Fixture'), self::CAPABILITY, null);
        }));
    }

    public function test_a_terminated_employment_revokes_the_authority(): void
    {
        $personId = trim($this->personWithAuthority('elig-term', [self::CAPABILITY])->id);
        // One row, admitted and then closed. "An older active row must not rescue it"
        // is not expressible here: `employments_one_open_per_person` means the newest
        // employment *is* the open one, so the only way for the newest row to be
        // terminated is for an employment to have been terminated — which is the
        // case this check exists for.
        $this->employments($personId, self::STATE_TERMINATED, '-2 minutes');

        $decision = app(AccessDecision::class)->decide(new Actor($personId, 'Eligibility Fixture'), self::CAPABILITY, null);

        $this->assertFalse($decision->allowed, 'the newest employment is terminated, so every scope the grant would have covered must be closed');
        $this->assertStringContainsString('authority grant is required', $decision->reason);
    }

    public function test_an_active_employment_keeps_the_authority(): void
    {
        $personId = trim($this->personWithAuthority('elig-active', [self::CAPABILITY])->id);
        $this->employments($personId, self::STATE_TERMINATED, '-3 minutes');
        $this->employments($personId, self::STATE_ACTIVE, '-2 minutes');

        $decision = app(AccessDecision::class)->decide(new Actor($personId, 'Eligibility Fixture'), self::CAPABILITY, null);

        $this->assertTrue($decision->allowed, 're-employment must restore authority without touching the grant');
    }

    public function test_eligibility_is_re_resolved_for_each_decision_rather_than_cached_across_them(): void
    {
        $personId = trim($this->personWithAuthority('elig-drift', [self::CAPABILITY])->id);
        $this->employments($personId, self::STATE_ACTIVE, '-3 minutes');
        $actor = new Actor($personId, 'Eligibility Fixture');

        $this->assertTrue(app(AccessDecision::class)->decide($actor, self::CAPABILITY, null)->allowed);

        // A later employment row appears between the two decisions — the same thing a
        // termination command does. The memo is scoped to one decision, so the second
        // one has to see it. Caching eligibility for the request instead of the
        // decision would answer this from the first pass and stay green while a person
        // keeps authority after their last day.
        // The termination command's own move: candidate -> active -> terminated on the
        // same row, because the schema permits only one open employment per person.
        DB::table('employments')->where('person_id', $personId)->where('lifecycle_state', self::STATE_ACTIVE)
            ->update(['lifecycle_state' => self::STATE_TERMINATED, 'updated_at' => now()->toDateTimeString()]);

        $this->assertFalse(app(AccessDecision::class)->decide($actor, self::CAPABILITY, null)->allowed);
    }

    public function test_one_decision_answers_eligibility_with_two_queries_whatever_it_consults_it_for(): void
    {
        $personId = trim($this->personWithAuthority('elig-cost', [self::CAPABILITY])->id);
        $this->employments($personId, self::STATE_CANDIDATE, '-3 minutes');
        DB::table('employments')->where('person_id', $personId)->update(['lifecycle_state' => self::STATE_TERMINATED]);
        $this->employments($personId, self::STATE_ACTIVE, '-2 minutes');

        $count = $this->employmentQueryCount(function () use ($personId): void {
            $decision = app(AccessDecision::class)->decide(new Actor($personId, 'Eligibility Fixture'), self::CAPABILITY, null);
            $this->assertTrue($decision->allowed);
        });

        // `exists()` plus the newest-row read: two, because eligibility is asked once
        // per person per decision and not once per scope source. The count is pinned
        // rather than merely bounded so that the fix cannot be "improved" into a
        // request-lifetime cache by someone reading a ceiling as permission.
        $this->assertSame(2, $count);
    }

    /* --- helpers ------------------------------------------------------------- */

    private const STATE_CANDIDATE = 'candidate';

    private const STATE_ACTIVE = 'active';

    private const STATE_TERMINATED = 'terminated';

    /**
     * Admit an employment and walk it to $finalState, back-dated so several rows can
     * be ordered by `created_at`.
     *
     * The row is created as `candidate` and moved, not written in its final state,
     * because two guards apply: `employments_lifecycle_guard()` refuses an
     * employment that did not begin as a candidate, and
     * `employments_one_open_per_person` allows only one open employment per person —
     * which is also why the revoked case below has to move a row to `terminated`
     * rather than insert a second one. A fixture that wrote `lifecycle_state` directly
     * would be the test overriding the domain it claims to verify.
     */
    private function employments(string $personId, string $finalState, string $offset = '-2 minutes'): void
    {
        $id = Str::uuid()->toString();
        DB::table('employments')->insert([
            'id' => $id,
            'person_id' => $personId,
            'lifecycle_state' => self::STATE_CANDIDATE,
            'created_at' => now()->modify($offset)->toDateTimeString(),
            'updated_at' => now()->modify($offset)->toDateTimeString(),
        ]);

        $path = match ($finalState) {
            self::STATE_CANDIDATE => [],
            self::STATE_ACTIVE => [self::STATE_ACTIVE],
            self::STATE_TERMINATED => [self::STATE_TERMINATED],
            default => [self::STATE_ACTIVE, $finalState],
        };

        foreach ($path as $state) {
            DB::table('employments')->where('id', $id)->update([
                'lifecycle_state' => $state,
                'updated_at' => now()->toDateTimeString(),
            ]);
        }
    }

    private function employmentQueryCount(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $operation();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return count(array_values(array_filter(
            $log,
            static fn (array $entry): bool => (bool) preg_match('/\bemployments\b/i', $entry['query'])
        )));
    }
}
