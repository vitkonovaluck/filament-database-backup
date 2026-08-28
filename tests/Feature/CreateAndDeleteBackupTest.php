<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Actions\CreateDatabaseBackupAction;
use Microcode\FilamentDatabaseBackup\Actions\DeleteBackupAction;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class CreateAndDeleteBackupTest extends TestCase
{
    public function test_create_backup_with_dump_callback_persists_row_and_file(): void
    {
        Storage::fake('backups');

        $backup = app(CreateDatabaseBackupAction::class)->execute(
            'My Backup Name',
            null,
            function (string $absolutePath): void {
                $dir = dirname($absolutePath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($absolutePath, "-- sql dump\nSELECT 1;\n");
            }
        );

        $this->assertInstanceOf(Backup::class, $backup);
        $this->assertSame('my-backup-name', $backup->name);
        $this->assertSame('dumps/my-backup-name.sql', $backup->path);
        $this->assertSame('backups', $backup->disk);
        $this->assertGreaterThan(0, $backup->size);
        $this->assertDatabaseHas('backups', [
            'id' => $backup->id,
            'name' => 'my-backup-name',
        ]);
        $this->assertTrue(Storage::disk('backups')->exists('dumps/my-backup-name.sql'));
    }

    public function test_delete_removes_file_and_soft_deletes_record(): void
    {
        Storage::fake('backups');

        $backup = app(CreateDatabaseBackupAction::class)->execute(
            'to-delete',
            null,
            function (string $absolutePath): void {
                $dir = dirname($absolutePath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($absolutePath, 'SQL');
            }
        );

        app(DeleteBackupAction::class)->execute($backup);

        $this->assertSoftDeleted('backups', ['id' => $backup->id]);
        $this->assertFalse(Storage::disk('backups')->exists('dumps/to-delete.sql'));
    }
}
