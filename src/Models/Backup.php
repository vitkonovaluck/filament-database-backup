<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Microcode\FilamentDatabaseBackup\Enums\BackupType;

/**
 * @property int $id
 * @property int|null $backup_schedule_id
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property int $size
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Backup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'backup_schedule_id',
        'name',
        'disk',
        'path',
        'size',
        'type',
    ];

    public function getTable(): string
    {
        return config('database-backup.tables.backups', 'backups');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'type' => BackupType::class,
            'backup_schedule_id' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BackupSchedule::class, 'backup_schedule_id');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;

        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return round($value, 2).' '.$unit;
            }
        }

        return round($value, 2).' PB';
    }
}
