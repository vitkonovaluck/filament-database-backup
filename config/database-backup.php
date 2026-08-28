<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('DATABASE_BACKUP_DISK', 'backups'),

    'path_prefix' => env('DATABASE_BACKUP_PATH_PREFIX'),

    'chunk_size' => (int) env('DATABASE_BACKUP_CHUNK_SIZE', 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | S3 (credentials via microcode/filament-integrations AWS driver)
    |--------------------------------------------------------------------------
    */
    's3' => [
        'enabled' => (bool) env('DATABASE_BACKUP_S3_ENABLED', true),
        'disk' => env('DATABASE_BACKUP_S3_DISK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Process / queue
    |--------------------------------------------------------------------------
    */
    'process_timeout_seconds' => (int) env('DATABASE_BACKUP_PROCESS_TIMEOUT', 600),

    'queue' => env('DATABASE_BACKUP_QUEUE', 'backups'),

    'job_timeout' => (int) env('DATABASE_BACKUP_JOB_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | MySQL / MariaDB CLI
    |--------------------------------------------------------------------------
    */
    'mysqldump_path' => env('DATABASE_BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    'mysql_path' => env('DATABASE_BACKUP_MYSQL_PATH', 'mysql'),

    'mysqldump_flags' => [
        '--single-transaction',
        '--routines',
        '--triggers',
    ],

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL CLI
    |--------------------------------------------------------------------------
    */
    'pg_dump_path' => env('DATABASE_BACKUP_PG_DUMP_PATH', 'pg_dump'),

    'psql_path' => env('DATABASE_BACKUP_PSQL_PATH', 'psql'),

    'pg_dump_flags' => [
        '--no-owner',
        '--no-acl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention defaults (schedules)
    |--------------------------------------------------------------------------
    */
    'default_retention_days' => (int) env('DATABASE_BACKUP_DEFAULT_RETENTION_DAYS', 30),

    'retention_days_min' => 1,

    'retention_days_max' => 365,

    /*
    |--------------------------------------------------------------------------
    | Table names (override if host already has conflicting tables)
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'backups' => env('DATABASE_BACKUP_TABLE_BACKUPS', 'backups'),
        'backup_schedules' => env('DATABASE_BACKUP_TABLE_SCHEDULES', 'backup_schedules'),
    ],

];
