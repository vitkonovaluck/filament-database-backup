<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Microcode\FilamentDatabaseBackup\Actions\RunBackupScheduleAction;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;
use Throwable;

final class RunBackupScheduleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(
        public readonly BackupSchedule $schedule,
    ) {
        $this->onQueue(FilamentDatabaseBackupPlugin::resolveQueue());
        $this->timeout = (int) config('database-backup.job_timeout', 600);
    }

    public function handle(RunBackupScheduleAction $runSchedule): void
    {
        $runSchedule->execute($this->schedule);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Backup schedule job failed.', [
            'schedule_id' => $this->schedule->id,
            'schedule_name' => $this->schedule->name,
            'message' => $exception?->getMessage(),
        ]);
    }
}
