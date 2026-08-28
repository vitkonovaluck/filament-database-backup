<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Microcode\FilamentDatabaseBackup\Actions\DeleteBackupAction;
use Microcode\FilamentDatabaseBackup\Actions\SyncBackupsFromDiskAction;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Jobs\CreateDatabaseBackupJob;
use Microcode\FilamentDatabaseBackup\Jobs\RestoreDatabaseBackupJob;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Support\BackupPath;
use UnitEnum;

class ListBackupsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'database-backups';

    protected string $view = 'filament-panels::pages.page';

    public static function getNavigationLabel(): string
    {
        return __('filament-database-backup::database-backup.navigation.backups');
    }

    public function getTitle(): string
    {
        return __('filament-database-backup::database-backup.navigation.backups');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentDatabaseBackupPlugin::tryGet()?->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|Heroicon|null
    {
        return FilamentDatabaseBackupPlugin::tryGet()?->getNavigationIcon() ?? Heroicon::OutlinedArchiveBox;
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentDatabaseBackupPlugin::tryGet()?->getNavigationSort();
    }

    public static function getCluster(): ?string
    {
        return FilamentDatabaseBackupPlugin::tryGet()?->getCluster();
    }

    public static function canAccess(): bool
    {
        return FilamentDatabaseBackupPlugin::canAuthorize();
    }

    public function mount(SyncBackupsFromDiskAction $syncBackups): void
    {
        $syncBackups->execute();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Backup::query())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('filament-database-backup::database-backup.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formatted_size')
                    ->label(__('filament-database-backup::database-backup.fields.size')),
                TextColumn::make('disk')
                    ->label(__('filament-database-backup::database-backup.fields.storage'))
                    ->formatStateUsing(function (string $state): string {
                        $localDisk = app(BackupDestinationResolverInterface::class)->localDisk();

                        return $state === $localDisk
                            ? __('filament-database-backup::database-backup.storage.local')
                            : __('filament-database-backup::database-backup.storage.s3');
                    }),
                TextColumn::make('created_at')
                    ->label(__('filament-database-backup::database-backup.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('filament-database-backup::database-backup.fields.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('filament-database-backup::database-backup.fields.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $q, mixed $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $q, mixed $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                Action::make('createBackup')
                    ->label(__('filament-database-backup::database-backup.actions.create'))
                    ->modalHeading(__('filament-database-backup::database-backup.actions.create'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-database-backup::database-backup.fields.name'))
                            ->required()
                            ->default(fn (): string => BackupPath::defaultBackupName())
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        CreateDatabaseBackupJob::dispatch($data['name']);

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.backup_queued'))
                            ->success()
                            ->send();
                    }),
                Action::make('refresh')
                    ->label(__('filament-database-backup::database-backup.actions.refresh'))
                    ->action(function (SyncBackupsFromDiskAction $syncBackups): void {
                        $syncBackups->execute();

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.synced'))
                            ->success()
                            ->send();
                    }),
                Action::make('manageSchedules')
                    ->label(__('filament-database-backup::database-backup.actions.manage_schedules'))
                    ->url(fn (): string => ManageBackupSchedulesPage::getUrl()),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('filament-database-backup::database-backup.actions.download'))
                    ->url(fn (Backup $record): string => route('database-backup.download', $record))
                    ->openUrlInNewTab(),
                Action::make('restore')
                    ->label(__('filament-database-backup::database-backup.actions.restore'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-database-backup::database-backup.confirm.restore_heading'))
                    ->modalDescription(__('filament-database-backup::database-backup.confirm.restore_description'))
                    ->action(function (Backup $record): void {
                        RestoreDatabaseBackupJob::dispatch($record);

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.restore_queued'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label(__('filament-database-backup::database-backup.actions.delete'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-database-backup::database-backup.confirm.delete_heading'))
                    ->modalDescription(__('filament-database-backup::database-backup.confirm.delete_description'))
                    ->action(function (Backup $record, DeleteBackupAction $deleteBackup): void {
                        $deleteBackup->execute($record);

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label(__('filament-database-backup::database-backup.actions.delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, DeleteBackupAction $deleteBackup): void {
                            foreach ($records as $record) {
                                /** @var Backup $record */
                                $deleteBackup->execute($record);
                            }

                            Notification::make()
                                ->title(__('filament-database-backup::database-backup.notifications.deleted'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading(__('filament-database-backup::database-backup.empty.backups_heading'))
            ->emptyStateDescription(__('filament-database-backup::database-backup.empty.backups_description'));
    }
}
