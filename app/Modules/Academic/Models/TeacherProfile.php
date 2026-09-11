<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Hr\Models\Employment;
use App\Modules\Hr\Models\EmploymentStatus;
use App\Modules\Identity\Models\Person;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Calendar\CalendarAuthority;

/** Canonical academic capability profile; not an identity or job-title alias. */
final class TeacherProfile extends Model
{
    public const STATE_PENDING = 'pending';

    public const STATE_ACTIVE = 'active';

    public const STATE_SUSPENDED = 'suspended';

    public const STATE_RETIRED = 'retired';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'person_id', 'employment_id', 'originating_branch_id', 'current_home_branch_id',
        'lifecycle_state', 'professional_title', 'profile_summary', 'approved_by', 'approved_at',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $profile): void {
            $status = EmploymentStatus::query()
                ->where('employment_id', $profile->employment_id)
                ->whereDate('effective_from', '<=', app(CalendarAuthority::class)->todayAsString())
                ->orderByDesc('effective_from')
                ->orderByDesc('seq')
                ->value('status');

            if ($status !== 'active') {
                throw BusinessRejection::forCode(
                    'academic.teacher_employment_inactive',
                    'a teacher profile requires employment that is active as of the registration date',
                );
            }
        });
    }

    /** @return list<string> */
    public static function allowedTransitions(string $state): array
    {
        return match ($state) {
            self::STATE_PENDING => [self::STATE_ACTIVE, self::STATE_RETIRED],
            self::STATE_ACTIVE => [self::STATE_ACTIVE, self::STATE_SUSPENDED, self::STATE_RETIRED],
            self::STATE_SUSPENDED => [self::STATE_ACTIVE, self::STATE_SUSPENDED, self::STATE_RETIRED],
            self::STATE_RETIRED => [self::STATE_RETIRED],
            default => [],
        };
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Employment, $this> */
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    /** @return HasMany<TeacherProfileStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(TeacherProfileStatus::class, 'teacher_profile_id');
    }

    /** @return HasMany<TeacherProfileBranch, $this> */
    public function branchAuthorizations(): HasMany
    {
        return $this->hasMany(TeacherProfileBranch::class, 'teacher_profile_id');
    }

    /** @return HasMany<TeacherQualification, $this> */
    public function qualifications(): HasMany
    {
        return $this->hasMany(TeacherQualification::class);
    }

    /** @return HasMany<TeacherSkillAuthority, $this> */
    public function skillAuthorities(): HasMany
    {
        return $this->hasMany(TeacherSkillAuthority::class);
    }

    /** @return HasMany<TeacherAvailability, $this> */
    public function availabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class, 'teacher_profile_id');
    }

    /** @return HasMany<TeacherWorkloadLimit, $this> */
    public function workloadLimits(): HasMany
    {
        return $this->hasMany(TeacherWorkloadLimit::class, 'teacher_profile_id');
    }
}
