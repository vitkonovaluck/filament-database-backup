<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Models\Backup;

final class CleanupExpiredBackupsAction
{
    public function __construct(
        private readonly DeleteBackupAction $deleteBackup,
    ) {}

    public function execute(): int
    {
        $globalDays = FilamentDatabaseBackupPlugin::resolveRetentionDays();
        $deleted = 0;

        $backups = Backup::query()->with('schedule')->get();

        foreach ($backups as $backup) {
            $days = $globalDays;

            if ($backup->schedule !== null) {
                $days = max(1, (int) $backup->schedule->retention_days);
            }

            $cutoff = now()->subDays($days);

            if ($backup->created_at !== null && $backup->created_at->lt($cutoff)) {
                $this->deleteBackup->execute($backup);
                $deleted++;
            }
        }

        return $deleted;
    }
}
