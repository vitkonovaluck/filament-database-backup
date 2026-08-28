<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Exceptions;

use Exception;

final class BackupException extends Exception
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self(__('filament-database-backup::database-backup.exceptions.unsupported_driver', [
            'driver' => $driver,
        ]));
    }

    public static function binaryMissing(string $path): self
    {
        return new self(__('filament-database-backup::database-backup.exceptions.binary_missing', [
            'path' => $path,
        ]));
    }

    public static function fileMissing(): self
    {
        return new self(__('filament-database-backup::database-backup.exceptions.file_missing'));
    }

    public static function pathUnsafe(): self
    {
        return new self(__('filament-database-backup::database-backup.exceptions.path_unsafe'));
    }

    public static function diskMismatch(): self
    {
        return new self(__('filament-database-backup::database-backup.exceptions.disk_mismatch'));
    }

    public static function dumpFailed(string $message = ''): self
    {
        $base = __('filament-database-backup::database-backup.exceptions.dump_failed');

        return new self($message !== '' ? "{$base}: {$message}" : $base);
    }

    public static function restoreFailed(string $message = ''): self
    {
        $base = __('filament-database-backup::database-backup.exceptions.restore_failed');

        return new self($message !== '' ? "{$base}: {$message}" : $base);
    }

    public static function s3UploadFailed(string $message = ''): self
    {
        $base = __('filament-database-backup::database-backup.exceptions.s3_upload_failed');

        return new self($message !== '' ? "{$base}: {$message}" : $base);
    }

    public static function unauthorized(): self
    {
        return new self(__('filament-database-backup::database-backup.exceptions.unauthorized'));
    }
}
