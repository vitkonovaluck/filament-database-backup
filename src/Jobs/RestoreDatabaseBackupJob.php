<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Microcode\FilamentDatabaseBackup\Actions\RestoreDatabaseBackupAction;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Throwable;

final class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(
        public readonly Backup $backup,
    ) {
        $this->onQueue(FilamentDatabaseBackupPlugin::resolveQueue());
        $this->timeout = (int) config('database-backup.job_timeout', 600);
    }

    public function handle(RestoreDatabaseBackupAction $restoreBackup): void
    {
        $restoreBackup->execute($this->backup);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Database restore job failed.', [
            'backup_id' => $this->backup->id,
            'path' => $this->backup->path,
            'message' => $exception?->getMessage(),
        ]);
    }
}
