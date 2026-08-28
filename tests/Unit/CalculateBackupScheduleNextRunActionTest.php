<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Unit;

use Illuminate\Support\Carbon;
use Microcode\FilamentDatabaseBackup\Actions\CalculateBackupScheduleNextRunAction;
use Microcode\FilamentDatabaseBackup\Enums\BackupFrequencyType;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class CalculateBackupScheduleNextRunActionTest extends TestCase
{
    public function test_hourly_adds_frequency_hours(): void
    {
        Carbon::setTestNow('2026-07-24 10:15:00');

        $schedule = new BackupSchedule([
            'frequency_type' => BackupFrequencyType::Hourly,
            'frequency_value' => 2,
        ]);

        $next = app(CalculateBackupScheduleNextRunAction::class)->execute($schedule);

        $this->assertSame('2026-07-24 12:15:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_daily_uses_today_when_time_still_ahead(): void
    {
        Carbon::setTestNow('2026-07-24 01:00:00');

        $schedule = new BackupSchedule([
            'frequency_type' => BackupFrequencyType::Daily,
            'frequency_value' => 1,
            'scheduled_time' => '02:00:00',
        ]);

        $next = app(CalculateBackupScheduleNextRunAction::class)->execute($schedule);

        $this->assertSame('2026-07-24 02:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_daily_adds_days_when_time_has_passed(): void
    {
        Carbon::setTestNow('2026-07-24 03:00:00');

        $schedule = new BackupSchedule([
            'frequency_type' => BackupFrequencyType::Daily,
            'frequency_value' => 3,
            'scheduled_time' => '02:00:00',
        ]);

        $next = app(CalculateBackupScheduleNextRunAction::class)->execute($schedule);

        $this->assertSame('2026-07-27 02:00:00', $next->format('Y-m-d H:i:s'));
    }
}
