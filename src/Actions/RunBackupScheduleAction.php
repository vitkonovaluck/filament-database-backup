<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Closure;
use Illuminate\Support\Str;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;
use Microcode\FilamentDatabaseBackup\Support\BackupPath;

final class RunBackupScheduleAction
{
    public function __construct(
        private readonly CreateDatabaseBackupAction $createBackup,
        private readonly CalculateBackupScheduleNextRunAction $calculateNextRun,
    ) {}

    /**
     * @param  Closure(string $absolutePath): void|null  $dumpCallback
     */
    public function execute(BackupSchedule $schedule, ?Closure $dumpCallback = null): Backup
    {
        $name = BackupPath::slugifyName(
            Str::slug($schedule->name).'-'.now()->format('Y-m-d-H-i-s')
        );

        $backup = $this->createBackup->execute(
            name: $name,
            backupScheduleId: $schedule->id,
            dumpCallback: $dumpCallback,
        );

        $schedule->forceFill([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun->execute($schedule),
        ])->save();

        return $backup;
    }
}
