<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Enums;

enum BackupDatabaseFamily: string
{
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';
}
