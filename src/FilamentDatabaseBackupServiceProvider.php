<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup;

use Microcode\FilamentDatabaseBackup\Commands\RunScheduledBackupsCommand;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Support\IntegrationsS3BackupDestinationResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentDatabaseBackupServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-database-backup')
            ->hasConfigFile('database-backup')
            ->hasTranslations()
            ->hasMigrations([
                'create_backup_schedules_table',
                'create_backups_table',
            ])
            ->hasCommand(RunScheduledBackupsCommand::class)
            ->hasRoutes('web');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(
            BackupDestinationResolverInterface::class,
            IntegrationsS3BackupDestinationResolver::class,
        );
    }
}
