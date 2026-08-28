<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Microcode\\FilamentDatabaseBackup\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentDatabaseBackupServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:2fl+KtvkdphvQyE8g1l+1xqjZ7qJ9qJ9qJ9qJ9qJ9qJ=');
        $app['config']->set('app.name', 'Backup Test App');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'app',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $app['config']->set('database.connections.mariadb', [
            'driver' => 'mariadb',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'app',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'app',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.disks.backups', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/backups'),
            'throw' => false,
        ]);

        $app['config']->set('filesystems.disks.s3', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/s3'),
            'throw' => false,
        ]);

        $app['config']->set('database-backup.disk', 'backups');
        $app['config']->set('database-backup.path_prefix', 'dumps');
        $app['config']->set('database-backup.queue', 'backups');
        $app['config']->set('database-backup.s3.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerPlugin(FilamentDatabaseBackupPlugin $plugin): void
    {
        // Binding for download authorization without a full Filament panel.
        $this->app->instance(FilamentDatabaseBackupPlugin::class, $plugin);
        $this->app->instance('filament-database-backup.plugin', $plugin);
    }
}
