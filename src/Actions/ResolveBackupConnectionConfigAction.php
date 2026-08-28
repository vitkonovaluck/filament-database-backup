<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Illuminate\Support\Facades\Config;
use Microcode\FilamentDatabaseBackup\DTOs\BackupConnectionConfigDTO;
use Microcode\FilamentDatabaseBackup\Enums\BackupDatabaseFamily;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;

final class ResolveBackupConnectionConfigAction
{
    public function execute(?string $connection = null): BackupConnectionConfigDTO
    {
        $connection ??= (string) Config::get('database.default');
        $config = Config::get("database.connections.{$connection}", []);

        if (! is_array($config) || $config === []) {
            throw BackupException::unsupportedDriver($connection);
        }

        $driver = (string) ($config['driver'] ?? '');

        $family = match ($driver) {
            'mysql', 'mariadb' => BackupDatabaseFamily::MySQL,
            'pgsql' => BackupDatabaseFamily::PostgreSQL,
            default => throw BackupException::unsupportedDriver($driver !== '' ? $driver : $connection),
        };

        $dumpFlags = match ($family) {
            BackupDatabaseFamily::MySQL => array_values(array_filter(
                (array) config('database-backup.mysqldump_flags', []),
                fn (mixed $flag): bool => is_string($flag) && $flag !== ''
            )),
            BackupDatabaseFamily::PostgreSQL => array_values(array_filter(
                (array) config('database-backup.pg_dump_flags', []),
                fn (mixed $flag): bool => is_string($flag) && $flag !== ''
            )),
        };

        return new BackupConnectionConfigDTO(
            family: $family,
            host: (string) ($config['host'] ?? '127.0.0.1'),
            port: (int) ($config['port'] ?? match ($family) {
                BackupDatabaseFamily::MySQL => 3306,
                BackupDatabaseFamily::PostgreSQL => 5432,
            }),
            database: (string) ($config['database'] ?? ''),
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
            dumpFlags: $dumpFlags,
        );
    }
}
