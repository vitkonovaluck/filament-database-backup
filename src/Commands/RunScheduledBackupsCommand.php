<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Commands;

use Illuminate\Console\Command;
use Microcode\FilamentDatabaseBackup\Actions\CleanupExpiredBackupsAction;
use Microcode\FilamentDatabaseBackup\Actions\RunBackupScheduleAction;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;

final class RunScheduledBackupsCommand extends Command
{
    protected $signature = 'database-backup:run-scheduled
                            {--force : Run all active schedules regardless of next_run_at}
                            {--schedule= : Run a single schedule by ID (forced)}';

    protected $description = 'Run due active database backup schedules';

    public function handle(
        RunBackupScheduleAction $runSchedule,
        CleanupExpiredBackupsAction $cleanupExpired,
    ): int {
        $scheduleId = $this->option('schedule');

        if ($scheduleId !== null && $scheduleId !== '') {
            $schedule = BackupSchedule::query()->findOrFail((int) $scheduleId);
            $runSchedule->execute($schedule);
            $this->info("Ran schedule #{$schedule->id} ({$schedule->name}).");
            $cleanupExpired->execute();

            return self::SUCCESS;
        }

        $query = BackupSchedule::query()->active();

        if (! $this->option('force')) {
            $query->due();
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $deleted = $cleanupExpired->execute();
            $this->info('No due backup schedules.');

            if ($deleted > 0) {
                $this->info("Removed {$deleted} expired backup(s).");
            }

            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $runSchedule->execute($schedule);
            $this->info("Ran schedule #{$schedule->id} ({$schedule->name}).");
        }

        $cleanupExpired->execute();

        return self::SUCCESS;
    }
}
