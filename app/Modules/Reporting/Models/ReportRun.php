<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\Model;

/**
 * Reproducible report execution pinned to a metric version; immutable
 * history. This row is evidence/projection, not a source-domain authority.
 */
final class ReportRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'metric_version_id', 'period_key', 'scope_type', 'scope_id', 'organization_id', 'filters', 'result', 'completeness', 'meta', 'reproducibility_hash', 'executed_by'];

    protected $casts = ['filters' => 'array', 'meta' => 'array'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw BusinessRejection::forCode('reporting.report_run_immutable', 'report runs are append-only evidence');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw BusinessRejection::forCode('reporting.report_run_immutable', 'report runs are append-only evidence');
    }

    public function forceDelete(): bool
    {
        throw BusinessRejection::forCode('reporting.report_run_immutable', 'report runs are append-only evidence');
    }
}
