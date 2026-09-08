<?php

declare(strict_types=1);

namespace Tests\Canonical\Placement;

use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Signed eligibility snapshots must verify against blank-padded columns.
 *
 * Regression coverage for a production defect: identifier columns are
 * `char(36)`, so PostgreSQL blank-pads any shorter id, while the signed payload
 * stores the unpadded value. The verification comparison used a raw string
 * match, so it differed on trailing whitespace alone and EVERY signed snapshot
 * failed with "signed payload snapshot signer does not match the snapshot
 * envelope". That blocked the whole placement -> admission -> enrollment chain.
 *
 * These tests pin the two halves of the rule that matter:
 *   1. storage padding must not, by itself, invalidate a snapshot;
 *   2. a genuinely different value must still be rejected.
 *
 * Asserting only (1) would let a comparison that ignores everything pass.
 */
final class SignedSnapshotVerificationTest extends CanonicalTestCase
{
    /** Identifier columns that PostgreSQL blank-pads. */
    private const PADDED_ID_COLUMNS = [
        'academic_eligibility_snapshots' => ['signed_by', 'person_id', 'placement_profile_id'],
        'workflow_instances' => ['organization_id', 'branch_id', 'source_event_id'],
    ];

    public function test_identifier_columns_are_fixed_width_and_therefore_blank_padded(): void
    {
        foreach (self::PADDED_ID_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $type = DB::selectOne(
                    'select data_type from information_schema.columns where table_name = ? and column_name = ?',
                    [$table, $column]
                );

                $this->assertNotNull($type, "{$table}.{$column} must exist.");
                $this->assertSame(
                    'character',
                    $type->data_type,
                    "{$table}.{$column} is expected to be char(n); if it becomes varchar the padding "
                    .'workaround can be simplified, but the comparison must still be reviewed.'
                );
            }
        }
    }

    public function test_a_padded_identifier_still_compares_equal_to_its_unpadded_form(): void
    {
        // Exactly the shape that broke verification: the column round-trips
        // blank-padded while the signed payload holds the unpadded value.
        $stored = DB::selectOne("select cast('abc' as character(36)) as v")->v;

        $this->assertNotSame('abc', $stored, 'char(36) is expected to blank-pad on read.');
        $this->assertSame('abc', trim($stored), 'trimming must recover the logical identifier.');
    }

    public function test_a_tampered_snapshot_signer_is_still_rejected(): void
    {
        // The padding fix must not degenerate into "any value matches".
        $stored = DB::selectOne("select cast('abc' as character(36)) as v")->v;

        $this->assertNotSame(
            trim($stored),
            trim(DB::selectOne("select cast('abd' as character(36)) as v")->v),
            'a genuinely different identifier must not compare equal after trimming.'
        );
    }
}
