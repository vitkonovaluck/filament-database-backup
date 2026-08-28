<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Support;

use Illuminate\Support\Str;

final class BackupPath
{
    public static function pathPrefix(): string
    {
        $configured = config('database-backup.path_prefix');

        if (is_string($configured) && $configured !== '') {
            return trim($configured, '/');
        }

        return Str::slug((string) config('app.name', 'app'));
    }

    public static function disk(): string
    {
        return (string) config('database-backup.disk', 'backups');
    }

    public static function slugifyName(string $name): string
    {
        $name = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name));
        $name = Str::beforeLast($name, '.sql');
        $name = Str::slug($name);

        if ($name === '' || str_contains($name, '..')) {
            $name = 'db-backup-'.now()->format('Y-m-d-H-i-s');
        }

        return $name;
    }

    public static function relativePath(string $slugifiedName): string
    {
        return self::pathPrefix().'/'.$slugifiedName.'.sql';
    }

    public static function defaultBackupName(): string
    {
        return 'db-backup-'.now()->format('Y-m-d-H-i-s');
    }
}
