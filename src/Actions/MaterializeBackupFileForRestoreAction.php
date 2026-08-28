<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\DTOs\BackupRestoreFileDTO;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\Models\Backup;

final class MaterializeBackupFileForRestoreAction
{
    public function __construct(
        private readonly BackupDestinationResolverInterface $destination,
    ) {}

    public function execute(Backup $backup): BackupRestoreFileDTO
    {
        $localDisk = Storage::disk($this->destination->localDisk());

        if ($backup->path !== '' && $localDisk->exists($backup->path)) {
            return new BackupRestoreFileDTO(
                absolutePath: $localDisk->path($backup->path),
                isTemporary: false,
            );
        }

        $stream = Storage::disk($backup->disk)->readStream($backup->path);

        if ($stream === false) {
            throw BackupException::fileMissing();
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'db-backup-');

        if ($tempPath === false) {
            fclose($stream);

            throw BackupException::restoreFailed('Unable to create a temporary restore file.');
        }

        $handle = fopen($tempPath, 'wb');

        if ($handle === false) {
            fclose($stream);
            @unlink($tempPath);

            throw BackupException::restoreFailed('Unable to open a temporary restore file.');
        }

        try {
            stream_copy_to_stream($stream, $handle);
        } finally {
            fclose($handle);
            fclose($stream);
        }

        return new BackupRestoreFileDTO(
            absolutePath: $tempPath,
            isTemporary: true,
        );
    }
}
