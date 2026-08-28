<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Microcode\FilamentDatabaseBackup\Enums\BackupDatabaseFamily;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;

final class ResolveBackupCliBinaryAction
{
    public function execute(BackupDatabaseFamily $family, string $purpose = 'dump'): string
    {
        $path = match ([$family, $purpose]) {
            [BackupDatabaseFamily::MySQL, 'dump'] => (string) config('database-backup.mysqldump_path', 'mysqldump'),
            [BackupDatabaseFamily::MySQL, 'restore'] => (string) config('database-backup.mysql_path', 'mysql'),
            [BackupDatabaseFamily::PostgreSQL, 'dump'] => (string) config('database-backup.pg_dump_path', 'pg_dump'),
            [BackupDatabaseFamily::PostgreSQL, 'restore'] => (string) config('database-backup.psql_path', 'psql'),
            default => (string) config('database-backup.mysqldump_path', 'mysqldump'),
        };

        if ($this->isAbsolutePath($path) && ! file_exists($path)) {
            throw BackupException::binaryMissing($path);
        }

        return $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
