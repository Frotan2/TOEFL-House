<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

use App\Support\Errors\BusinessRejection;
use Cron\CronExpression;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * The durable JobSchedule expression is executable configuration, rather than
 * a display-only label. Keep parsing in one place so registration fails before
 * an invalid schedule can strand the minute runner at runtime.
 */
final class ScheduleExpression
{
    public static function normalize(string $expression): string
    {
        $normalized = trim($expression);
        if ($normalized === '') {
            throw BusinessRejection::forCode('integrations.job_schedule_invalid', 'a scheduled job requires a valid cron expression');
        }

        self::parse($normalized);

        return $normalized;
    }

    public static function isDue(string $expression, DateTimeInterface $at): bool
    {
        return self::parse($expression)->isDue($at);
    }

    private static function parse(string $expression): CronExpression
    {
        try {
            return CronExpression::factory($expression);
        } catch (InvalidArgumentException) {
            throw BusinessRejection::forCode('integrations.job_schedule_invalid', 'a scheduled job requires a valid cron expression');
        }
    }
}
