<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Models\Backup;

final class DeleteBackupAction
{
    public function __construct(
        private readonly BackupDestinationResolverInterface $destination,
    ) {}

    public function execute(Backup $backup): void
    {
        $disk = Storage::disk($backup->disk);

        if ($backup->path !== '' && $disk->exists($backup->path)) {
            $disk->delete($backup->path);
        }

        $localDiskName = $this->destination->localDisk();

        if ($backup->disk !== $localDiskName && $backup->path !== '') {
            $localDisk = Storage::disk($localDiskName);

            if ($localDisk->exists($backup->path)) {
                $localDisk->delete($backup->path);
            }
        }

        $backup->delete();
    }
}
