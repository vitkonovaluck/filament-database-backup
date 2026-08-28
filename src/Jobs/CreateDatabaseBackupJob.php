<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Microcode\FilamentDatabaseBackup\Actions\CreateDatabaseBackupAction;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Throwable;

final class CreateDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(
        public readonly string $name,
        public readonly ?int $backupScheduleId = null,
    ) {
        $this->onQueue(FilamentDatabaseBackupPlugin::resolveQueue());
        $this->timeout = (int) config('database-backup.job_timeout', 600);
    }

    public function handle(CreateDatabaseBackupAction $createBackup): void
    {
        $createBackup->execute($this->name, $this->backupScheduleId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Database backup job failed.', [
            'name' => $this->name,
            'backup_schedule_id' => $this->backupScheduleId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
