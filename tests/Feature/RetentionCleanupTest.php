<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Actions\CleanupExpiredBackupsAction;
use Microcode\FilamentDatabaseBackup\Enums\BackupFrequencyType;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class RetentionCleanupTest extends TestCase
{
    public function test_global_plugin_retention_deletes_old_manual_backups(): void
    {
        Storage::fake('backups');
        Carbon::setTestNow('2026-07-24 12:00:00');

        $this->app->instance(
            FilamentDatabaseBackupPlugin::class,
            FilamentDatabaseBackupPlugin::make()->retentionDays(7)
        );

        $expiredManual = $this->createBackupFile('expired-manual', null, now()->subDays(10));
        $freshManual = $this->createBackupFile('fresh-manual', null, now()->subDays(2));

        app(CleanupExpiredBackupsAction::class)->execute();

        $this->assertSoftDeleted('backups', ['id' => $expiredManual->id]);
        $this->assertDatabaseHas('backups', ['id' => $freshManual->id, 'deleted_at' => null]);
    }

    public function test_schedule_retention_overrides_plugin_default(): void
    {
        Storage::fake('backups');
        Carbon::setTestNow('2026-07-24 12:00:00');

        $this->app->instance(
            FilamentDatabaseBackupPlugin::class,
            FilamentDatabaseBackupPlugin::make()->retentionDays(3)
        );

        $schedule = BackupSchedule::query()->create([
            'name' => 'A',
            'frequency_type' => BackupFrequencyType::Daily,
            'frequency_value' => 1,
            'scheduled_time' => '02:00:00',
            'is_active' => true,
            'retention_days' => 14,
        ]);

        $withinSchedule = $this->createBackupFile('schedule-keep', $schedule->id, now()->subDays(10));
        $expiredManual = $this->createBackupFile('manual-gone', null, now()->subDays(10));

        app(CleanupExpiredBackupsAction::class)->execute();

        $this->assertDatabaseHas('backups', ['id' => $withinSchedule->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('backups', ['id' => $expiredManual->id]);
    }

    private function createBackupFile(string $name, ?int $scheduleId, Carbon $createdAt): Backup
    {
        $path = "dumps/{$name}.sql";
        Storage::disk('backups')->put($path, 'SQL');

        $backup = Backup::query()->create([
            'backup_schedule_id' => $scheduleId,
            'name' => $name,
            'disk' => 'backups',
            'path' => $path,
            'size' => 3,
            'type' => 'database',
        ]);

        $backup->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $backup->refresh();
    }
}
