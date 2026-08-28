<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Models\Backup;

final class AssertBackupFileAccessAction
{
    public function __construct(
        private readonly BackupDestinationResolverInterface $destination,
    ) {}

    public function execute(Backup $backup, bool $authorize = true): void
    {
        if ($authorize && ! FilamentDatabaseBackupPlugin::canAuthorize()) {
            throw BackupException::unauthorized();
        }

        if (! in_array($backup->disk, $this->destination->allowedDisks(), true)) {
            throw BackupException::diskMismatch();
        }

        $path = $backup->path;

        if ($path === '' || $path === '.' || $path === '..') {
            throw BackupException::pathUnsafe();
        }

        if (str_contains($path, '..')) {
            throw BackupException::pathUnsafe();
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw BackupException::pathUnsafe();
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            throw BackupException::pathUnsafe();
        }

        if (! Storage::disk($backup->disk)->exists($path)) {
            throw BackupException::fileMissing();
        }
    }

    public function assertRelativePath(string $path): void
    {
        if ($path === '' || str_contains($path, '..')) {
            throw BackupException::pathUnsafe();
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw BackupException::pathUnsafe();
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            throw BackupException::pathUnsafe();
        }
    }
}
