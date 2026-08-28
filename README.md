# Filament Database Backup

Laravel 12 / 13 + Filament 4 / 5 plugin for **database** backup, restore, and scheduling.

Supports **MySQL / MariaDB / PostgreSQL** via CLI tools (`mysqldump` / `mysql`, `pg_dump` / `psql`).

Package: `microcode/filament-database-backup`  
Namespace: `Microcode\FilamentDatabaseBackup`

**Not supported:** SQLite, application file backups, Spatie Backup.

## Install

```bash
composer require microcode/filament-database-backup
php artisan vendor:publish --tag="filament-database-backup-config"
php artisan vendor:publish --tag="filament-database-backup-migrations"
php artisan migrate
```

This package requires [`microcode/filament-integrations`](https://packagist.org/packages/microcode/filament-integrations). Register both plugins on the Filament panel. Enable the `aws` driver if you want backups stored on S3.

```php
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentIntegrations\IntegrationsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            IntegrationsPlugin::make()
                ->navigationGroup('Settings')
                ->canAccessSettings(fn (): bool => true)
                ->only(['aws'])
        )
        ->plugin(
            FilamentDatabaseBackupPlugin::make()
                ->authorize(fn (): bool => auth()->user()?->can('manage-backups') ?? false)
                ->navigationGroup('Settings')
                ->navigationSort(30)
                ->retentionDays(30)
                // ->cluster(SomeCluster::class)
                // ->queue('backups')
        );
}
```

Authorization is injected via `->authorize(...)`. Navigation group / icon / sort / cluster are configurable on the plugin. No host roles or clusters are hardcoded.

## Filesystem disk

Add a private local disk (e.g. in `config/filesystems.php`). Dumps always start here; when AWS is configured this disk keeps **only the latest** `.sql` file.

```php
'backups' => [
    'driver' => 'local',
    'root' => storage_path('app/backups'),
    'visibility' => 'private',
    'throw' => false,
],
```

Config key `database-backup.disk` defaults to `backups`.

## S3 storage

When the Integrations AWS driver has `access_key_id`, `secret_access_key`, `region`, and `bucket`:

- the dump is uploaded to the Laravel S3 disk (default name `s3`, or `FILAMENT_INTEGRATIONS_AWS_DISK` / `DATABASE_BACKUP_S3_DISK`);
- the catalog row stores that remote disk;
- older dumps live on S3 until retention deletes them;
- locally only the newest file remains.

Host requirements:

1. Enable `aws` on `IntegrationsPlugin` and save credentials in the Integrations UI.
2. Define an S3 disk stub in `config/filesystems.php` (`driver => s3`).
3. Install `league/flysystem-aws-s3-v3`.

Set `DATABASE_BACKUP_S3_ENABLED=false` to force local-only storage.

## Retention

Global default (manual backups and S3 copies):

```php
FilamentDatabaseBackupPlugin::make()->retentionDays(30)
```

If omitted, `config('database-backup.default_retention_days')` is used (env `DATABASE_BACKUP_DEFAULT_RETENTION_DAYS`). Each schedule can override this for its own backups.

Cleanup runs after every successful backup and from `database-backup:run-scheduled`.

## Queue worker

All create / restore / run-now work is queued.

```bash
php artisan queue:work --queue=backups --tries=1 --timeout=600
```

## Scheduler

In `routes/console.php` (or the host scheduler):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('database-backup:run-scheduled')->everyMinute();
```

Command options:

- `--force` — run all active schedules
- `--schedule=` — run one schedule by ID (forced)

## Environment / config

Published file: `config/database-backup.php`

| Key | Purpose |
|-----|---------|
| `disk` | Local filesystem disk name |
| `path_prefix` | Folder under disk (default: slug of `app.name`) |
| `s3.enabled` / `s3.disk` | Use S3 when AWS is configured; optional disk name override |
| `queue` / `job_timeout` | Queue name and job timeout |
| `process_timeout_seconds` | CLI process timeout |
| `mysqldump_path` / `mysql_path` / `mysqldump_flags` | MySQL tools |
| `pg_dump_path` / `psql_path` / `pg_dump_flags` | PostgreSQL tools |
| `default_retention_days` / `retention_days_min` / `retention_days_max` | Retention window |
| `tables.backups` / `tables.backup_schedules` | Table name overrides |

Passwords are passed only via `MYSQL_PWD` / `PGPASSWORD` process env — never on argv or in logs.

## Features

1. Manual backup (queued dump → `.sql` on disk or S3 + catalog row)
2. Restore with confirmation (queued; local cache or S3 download; **ALL CURRENT DATA WILL BE LOST**)
3. Catalog sync from `{path_prefix}/*.sql` on the canonical disk
4. Secure download route `database-backup.download` (authorize + path checks)
5. Schedules: hourly / daily, optional per-schedule retention override
6. Artisan `database-backup:run-scheduled`

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint
```

Package tests use an in-memory SQLite connection for the **catalog** tables only. Dump/restore CLI paths are covered with callbacks / unit mapping tests — no real `mysqldump` is required.

## License

MIT
