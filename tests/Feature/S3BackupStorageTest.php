<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Actions\AssertBackupFileAccessAction;
use Microcode\FilamentDatabaseBackup\Actions\CreateDatabaseBackupAction;
use Microcode\FilamentDatabaseBackup\Actions\DeleteBackupAction;
use Microcode\FilamentDatabaseBackup\Actions\MaterializeBackupFileForRestoreAction;
use Microcode\FilamentDatabaseBackup\Actions\SyncBackupsFromDiskAction;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Tests\Support\FakeBackupDestinationResolver;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class S3BackupStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
        Storage::fake('s3');

        $this->app->instance(
            BackupDestinationResolverInterface::class,
            new FakeBackupDestinationResolver(remote: true)
        );
    }

    public function test_create_uploads_to_s3_and_keeps_local_file(): void
    {
        $backup = $this->createDump('first-remote');

        $this->assertSame('s3', $backup->disk);
        $this->assertTrue(Storage::disk('s3')->exists('dumps/first-remote.sql'));
        $this->assertTrue(Storage::disk('backups')->exists('dumps/first-remote.sql'));
    }

    public function test_second_create_prunes_previous_local_copy(): void
    {
        $this->createDump('first-remote');
        $second = $this->createDump('second-remote');

        $this->assertTrue(Storage::disk('s3')->exists('dumps/first-remote.sql'));
        $this->assertTrue(Storage::disk('s3')->exists('dumps/second-remote.sql'));
        $this->assertFalse(Storage::disk('backups')->exists('dumps/first-remote.sql'));
        $this->assertTrue(Storage::disk('backups')->exists('dumps/second-remote.sql'));
        $this->assertSame('s3', $second->disk);
    }

    public function test_materialize_restore_uses_s3_when_local_missing(): void
    {
        $backup = $this->createDump('remote-only');
        Storage::disk('backups')->delete($backup->path);

        $source = app(MaterializeBackupFileForRestoreAction::class)->execute($backup);

        $this->assertTrue($source->isTemporary);
        $this->assertFileExists($source->absolutePath);
        $this->assertSame("-- sql dump\nSELECT 1;\n", file_get_contents($source->absolutePath));

        unlink($source->absolutePath);
    }

    public function test_delete_removes_s3_and_local_cache(): void
    {
        $backup = $this->createDump('to-delete-remote');

        app(DeleteBackupAction::class)->execute($backup);

        $this->assertSoftDeleted('backups', ['id' => $backup->id]);
        $this->assertFalse(Storage::disk('s3')->exists('dumps/to-delete-remote.sql'));
        $this->assertFalse(Storage::disk('backups')->exists('dumps/to-delete-remote.sql'));
    }

    public function test_access_check_allows_s3_disk(): void
    {
        $backup = $this->createDump('access-s3');

        app(AssertBackupFileAccessAction::class)->execute($backup, authorize: false);

        $this->assertTrue(true);
    }

    public function test_sync_scans_s3_catalog_disk(): void
    {
        Storage::disk('s3')->put('dumps/from-s3.sql', 'SELECT 1;');

        $count = app(SyncBackupsFromDiskAction::class)->execute();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('backups', [
            'name' => 'from-s3',
            'path' => 'dumps/from-s3.sql',
            'disk' => 's3',
        ]);
    }

    private function createDump(string $name): Backup
    {
        return app(CreateDatabaseBackupAction::class)->execute(
            $name,
            null,
            function (string $absolutePath): void {
                $dir = dirname($absolutePath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($absolutePath, "-- sql dump\nSELECT 1;\n");
            }
        );
    }
}
