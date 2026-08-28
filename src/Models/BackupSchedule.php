<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Microcode\FilamentDatabaseBackup\Enums\BackupFrequencyType;

/**
 * @property int $id
 * @property string $name
 * @property BackupFrequencyType $frequency_type
 * @property int $frequency_value
 * @property string|null $scheduled_time
 * @property bool $is_active
 * @property int $retention_days
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BackupSchedule extends Model
{
    protected $fillable = [
        'name',
        'frequency_type',
        'frequency_value',
        'scheduled_time',
        'is_active',
        'retention_days',
        'last_run_at',
        'next_run_at',
    ];

    public function getTable(): string
    {
        return config('database-backup.tables.backup_schedules', 'backup_schedules');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency_type' => BackupFrequencyType::class,
            'frequency_value' => 'integer',
            'is_active' => 'boolean',
            'retention_days' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'backup_schedule_id');
    }

    /**
     * @param  Builder<BackupSchedule>  $query
     * @return Builder<BackupSchedule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<BackupSchedule>  $query
     * @return Builder<BackupSchedule>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    public function getFrequencyLabelAttribute(): string
    {
        $time = $this->scheduled_time
            ? substr((string) $this->scheduled_time, 0, 5)
            : null;

        return match ($this->frequency_type) {
            BackupFrequencyType::Hourly => $this->frequency_value === 1
                ? __('filament-database-backup::database-backup.frequency.every_hour')
                : __('filament-database-backup::database-backup.frequency.every_hours', [
                    'count' => $this->frequency_value,
                ]),
            BackupFrequencyType::Daily => $this->frequency_value === 1
                ? __('filament-database-backup::database-backup.frequency.daily_at', [
                    'time' => $time ?? '00:00',
                ])
                : __('filament-database-backup::database-backup.frequency.every_days_at', [
                    'count' => $this->frequency_value,
                    'time' => $time ?? '00:00',
                ]),
        };
    }
}
