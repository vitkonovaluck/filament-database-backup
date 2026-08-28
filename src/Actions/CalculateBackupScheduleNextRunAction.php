<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Microcode\FilamentDatabaseBackup\Enums\BackupFrequencyType;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;

final class CalculateBackupScheduleNextRunAction
{
    public function execute(BackupSchedule $schedule, ?CarbonInterface $from = null): Carbon
    {
        $from = Carbon::parse($from ?? now());

        return match ($schedule->frequency_type) {
            BackupFrequencyType::Hourly => $from->copy()->addHours(max(1, (int) $schedule->frequency_value)),
            BackupFrequencyType::Daily => $this->nextDailyRun($schedule, $from),
        };
    }

    private function nextDailyRun(BackupSchedule $schedule, Carbon $from): Carbon
    {
        $time = $schedule->scheduled_time ?? '00:00:00';
        $time = strlen((string) $time) === 5 ? $time.':00' : (string) $time;

        $candidate = $from->copy()->setTimeFromTimeString($time);
        $days = max(1, (int) $schedule->frequency_value);

        if ($candidate->greaterThan($from)) {
            return $candidate;
        }

        return $candidate->addDays($days);
    }
}
