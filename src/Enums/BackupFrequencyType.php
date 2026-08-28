<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Enums;

use Filament\Support\Contracts\HasLabel;

enum BackupFrequencyType: string implements HasLabel
{
    case Hourly = 'hourly';
    case Daily = 'daily';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => __('filament-database-backup::database-backup.frequency.hourly'),
            self::Daily => __('filament-database-backup::database-backup.frequency.daily'),
        };
    }
}
