<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Actions\SyncBackupsFromDiskAction;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class SyncBackupsFromDiskTest extends TestCase
{
    public function test_sync_upserts_missing_files(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('dumps/from-disk.sql', 'SELECT 1;');

        $count = app(SyncBackupsFromDiskAction::class)->execute();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('backups', [
            'name' => 'from-disk',
            'path' => 'dumps/from-disk.sql',
            'disk' => 'backups',
        ]);
    }

    public function test_sync_does_not_wipe_catalog_when_disk_empty(): void
    {
        Storage::fake('backups');

        Backup::query()->create([
            'name' => 'keep-me',
            'disk' => 'backups',
            'path' => 'dumps/keep-me.sql',
            'size' => 10,
            'type' => 'database',
        ]);

        $count = app(SyncBackupsFromDiskAction::class)->execute();

        $this->assertSame(0, $count);
        $this->assertDatabaseHas('backups', ['name' => 'keep-me']);
        $this->assertSame(1, Backup::query()->count());
    }
}
