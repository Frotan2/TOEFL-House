<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cancellation is a valid terminal path from requested, before an
        // approval exists. Keep the approval requirement for every other
        // post-request state.
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_approval_actor_check');
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_approval_actor_check CHECK (lifecycle_state IN ('requested','cancelled') OR approved_by IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_approval_actor_check');
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_approval_actor_check CHECK (lifecycle_state = 'requested' OR approved_by IS NOT NULL)");
    }
};
