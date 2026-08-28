<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Actions\AssertBackupFileAccessAction;
use Microcode\FilamentDatabaseBackup\Actions\CreateDatabaseBackupAction;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class BackupDownloadSecurityTest extends TestCase
{
    public function test_unauthorized_download_returns_403(): void
    {
        Storage::fake('backups');

        $backup = app(CreateDatabaseBackupAction::class)->execute(
            'secure',
            null,
            function (string $absolutePath): void {
                $dir = dirname($absolutePath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($absolutePath, 'SQL');
            }
        );

        $this->app->instance(
            FilamentDatabaseBackupPlugin::class,
            FilamentDatabaseBackupPlugin::make()->authorize(false)
        );

        $this->get(route('database-backup.download', $backup))
            ->assertForbidden();
    }

    public function test_path_traversal_is_rejected(): void
    {
        Storage::fake('backups');

        $backup = Backup::query()->create([
            'name' => 'evil',
            'disk' => 'backups',
            'path' => '../etc/passwd.sql',
            'size' => 3,
            'type' => 'database',
        ]);

        $this->expectException(BackupException::class);

        app(AssertBackupFileAccessAction::class)->execute($backup, authorize: false);
    }

    public function test_absolute_windows_path_is_rejected(): void
    {
        $this->expectException(BackupException::class);

        app(AssertBackupFileAccessAction::class)->assertRelativePath('C:\\Windows\\system32\\evil.sql');
    }
}
