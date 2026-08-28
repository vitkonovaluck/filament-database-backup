<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Microcode\FilamentDatabaseBackup\Actions\CalculateBackupScheduleNextRunAction;
use Microcode\FilamentDatabaseBackup\Enums\BackupFrequencyType;
use Microcode\FilamentDatabaseBackup\FilamentDatabaseBackupPlugin;
use Microcode\FilamentDatabaseBackup\Jobs\RunBackupScheduleJob;
use Microcode\FilamentDatabaseBackup\Models\BackupSchedule;
use UnitEnum;

class ManageBackupSchedulesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'database-backup-schedules';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-panels::pages.page';

    public static function getNavigationLabel(): string
    {
        return __('filament-database-backup::database-backup.navigation.schedules');
    }

    public function getTitle(): string
    {
        return __('filament-database-backup::database-backup.navigation.schedules');
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
        $sort = FilamentDatabaseBackupPlugin::tryGet()?->getNavigationSort();

        return $sort === null ? null : $sort + 1;
    }

    public static function getCluster(): ?string
    {
        return FilamentDatabaseBackupPlugin::tryGet()?->getCluster();
    }

    public static function canAccess(): bool
    {
        return FilamentDatabaseBackupPlugin::canAuthorize();
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
            ->query(BackupSchedule::query())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('filament-database-backup::database-backup.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('frequency_label')
                    ->label(__('filament-database-backup::database-backup.fields.frequency')),
                IconColumn::make('is_active')
                    ->label(__('filament-database-backup::database-backup.fields.is_active'))
                    ->boolean(),
                TextColumn::make('retention_days')
                    ->label(__('filament-database-backup::database-backup.fields.retention_days'))
                    ->sortable(),
                TextColumn::make('last_run_at')
                    ->label(__('filament-database-backup::database-backup.fields.last_run_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('next_run_at')
                    ->label(__('filament-database-backup::database-backup.fields.next_run_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('filament-database-backup::database-backup.actions.create_schedule'))
                    ->model(BackupSchedule::class)
                    ->schema($this->scheduleFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->normalizeScheduleData($data);
                    })
                    ->using(function (array $data, CalculateBackupScheduleNextRunAction $calculateNextRun): BackupSchedule {
                        $schedule = BackupSchedule::query()->create($data);
                        $schedule->forceFill([
                            'next_run_at' => $calculateNextRun->execute($schedule),
                        ])->save();

                        return $schedule;
                    })
                    ->successNotificationTitle(__('filament-database-backup::database-backup.notifications.schedule_created')),
            ])
            ->recordActions([
                EditAction::make()
                    ->model(BackupSchedule::class)
                    ->schema($this->scheduleFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->normalizeScheduleData($data);
                    })
                    ->using(function (BackupSchedule $record, array $data, CalculateBackupScheduleNextRunAction $calculateNextRun): BackupSchedule {
                        $record->fill($data);
                        $record->next_run_at = $calculateNextRun->execute($record);
                        $record->save();

                        return $record;
                    })
                    ->successNotificationTitle(__('filament-database-backup::database-backup.notifications.schedule_updated')),
                Action::make('activate')
                    ->label(__('filament-database-backup::database-backup.actions.activate'))
                    ->visible(fn (BackupSchedule $record): bool => ! $record->is_active)
                    ->action(function (BackupSchedule $record, CalculateBackupScheduleNextRunAction $calculateNextRun): void {
                        $record->forceFill([
                            'is_active' => true,
                            'next_run_at' => $calculateNextRun->execute($record),
                        ])->save();

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.schedule_activated'))
                            ->success()
                            ->send();
                    }),
                Action::make('deactivate')
                    ->label(__('filament-database-backup::database-backup.actions.deactivate'))
                    ->visible(fn (BackupSchedule $record): bool => $record->is_active)
                    ->action(function (BackupSchedule $record): void {
                        $record->update(['is_active' => false]);

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.schedule_deactivated'))
                            ->success()
                            ->send();
                    }),
                Action::make('runNow')
                    ->label(__('filament-database-backup::database-backup.actions.run_now'))
                    ->action(function (BackupSchedule $record): void {
                        RunBackupScheduleJob::dispatch($record);

                        Notification::make()
                            ->title(__('filament-database-backup::database-backup.notifications.schedule_run_queued'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->modalHeading(__('filament-database-backup::database-backup.confirm.delete_schedule_heading'))
                    ->modalDescription(__('filament-database-backup::database-backup.confirm.delete_schedule_description'))
                    ->successNotificationTitle(__('filament-database-backup::database-backup.notifications.schedule_deleted')),
            ])
            ->emptyStateHeading(__('filament-database-backup::database-backup.empty.schedules_heading'))
            ->emptyStateDescription(__('filament-database-backup::database-backup.empty.schedules_description'));
    }

    /**
     * @return array<int, mixed>
     */
    private function scheduleFormSchema(): array
    {
        $min = (int) config('database-backup.retention_days_min', 1);
        $max = (int) config('database-backup.retention_days_max', 365);
        $defaultRetention = FilamentDatabaseBackupPlugin::resolveRetentionDays();

        return [
            TextInput::make('name')
                ->label(__('filament-database-backup::database-backup.fields.name'))
                ->required()
                ->maxLength(255),
            Toggle::make('is_active')
                ->label(__('filament-database-backup::database-backup.fields.is_active'))
                ->default(true),
            Select::make('frequency_type')
                ->label(__('filament-database-backup::database-backup.fields.frequency_type'))
                ->options(BackupFrequencyType::class)
                ->required()
                ->live(),
            TextInput::make('frequency_value')
                ->label(__('filament-database-backup::database-backup.fields.frequency_value'))
                ->numeric()
                ->required()
                ->minValue(1)
                ->default(1),
            TimePicker::make('scheduled_time')
                ->label(__('filament-database-backup::database-backup.fields.scheduled_time'))
                ->seconds(false)
                ->required(fn (Get $get): bool => $get('frequency_type') === BackupFrequencyType::Daily->value
                    || $get('frequency_type') === BackupFrequencyType::Daily)
                ->visible(fn (Get $get): bool => $get('frequency_type') === BackupFrequencyType::Daily->value
                    || $get('frequency_type') === BackupFrequencyType::Daily),
            TextInput::make('retention_days')
                ->label(__('filament-database-backup::database-backup.fields.retention_days'))
                ->helperText(__('filament-database-backup::database-backup.helpers.retention_days'))
                ->numeric()
                ->required()
                ->minValue($min)
                ->maxValue($max)
                ->default($defaultRetention),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeScheduleData(array $data): array
    {
        $type = $data['frequency_type'] ?? null;

        if ($type instanceof BackupFrequencyType) {
            $data['frequency_type'] = $type->value;
            $type = $type->value;
        }

        if ($type !== BackupFrequencyType::Daily->value) {
            $data['scheduled_time'] = null;
        }

        $data['frequency_value'] = max(1, (int) ($data['frequency_value'] ?? 1));

        $min = (int) config('database-backup.retention_days_min', 1);
        $max = (int) config('database-backup.retention_days_max', 365);
        $data['retention_days'] = min($max, max($min, (int) ($data['retention_days'] ?? FilamentDatabaseBackupPlugin::resolveRetentionDays())));

        return $data;
    }
}
