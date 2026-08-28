<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Microcode\FilamentDatabaseBackup\Filament\Pages\ListBackupsPage;
use Microcode\FilamentDatabaseBackup\Filament\Pages\ManageBackupSchedulesPage;
use UnitEnum;

final class FilamentDatabaseBackupPlugin implements Plugin
{
    protected Closure|bool|null $authorizeCallback = null;

    protected string|UnitEnum|null $navigationGroup = null;

    protected string|BackedEnum|Heroicon|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected ?int $navigationSort = null;

    protected ?string $cluster = null;

    protected ?string $backupsPage = null;

    protected ?string $schedulesPage = null;

    protected ?string $queue = null;

    protected ?int $retentionDays = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public static function tryGet(): ?static
    {
        try {
            return self::get();
        } catch (\Throwable) {
            if (app()->bound(static::class)) {
                $bound = app(static::class);

                if ($bound instanceof static) {
                    return $bound;
                }
            }

            return null;
        }
    }

    public function getId(): string
    {
        return 'filament-database-backup';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            $this->getBackupsPageClass(),
            $this->getSchedulesPageClass(),
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function authorize(Closure|bool|null $callback): static
    {
        $this->authorizeCallback = $callback;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|Heroicon|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function cluster(?string $clusterClass): static
    {
        $this->cluster = $clusterClass;

        return $this;
    }

    public function backupsPage(?string $pageClass): static
    {
        $this->backupsPage = $pageClass;

        return $this;
    }

    public function schedulesPage(?string $pageClass): static
    {
        $this->schedulesPage = $pageClass;

        return $this;
    }

    public function queue(?string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function retentionDays(?int $days): static
    {
        $this->retentionDays = $days;

        return $this;
    }

    public function getAuthorizeCallback(): Closure|bool|null
    {
        return $this->authorizeCallback;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function getNavigationIcon(): string|BackedEnum|Heroicon|null
    {
        return $this->navigationIcon;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function getCluster(): ?string
    {
        return $this->cluster;
    }

    public function getBackupsPageClass(): string
    {
        return $this->backupsPage ?? ListBackupsPage::class;
    }

    public function getSchedulesPageClass(): string
    {
        return $this->schedulesPage ?? ManageBackupSchedulesPage::class;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function getRetentionDays(): ?int
    {
        return $this->retentionDays;
    }

    public static function resolveQueue(): string
    {
        $plugin = self::tryGet();

        if ($plugin !== null && $plugin->getQueue() !== null && $plugin->getQueue() !== '') {
            return $plugin->getQueue();
        }

        return (string) config('database-backup.queue', 'backups');
    }

    public static function resolveRetentionDays(): int
    {
        $min = (int) config('database-backup.retention_days_min', 1);
        $max = (int) config('database-backup.retention_days_max', 365);

        $plugin = self::tryGet();
        $days = $plugin?->getRetentionDays();

        if ($days === null || $days <= 0) {
            $days = (int) config('database-backup.default_retention_days', 30);
        }

        return min($max, max($min, $days));
    }

    public static function canAuthorize(): bool
    {
        $plugin = self::tryGet();

        if ($plugin === null) {
            return true;
        }

        $callback = $plugin->getAuthorizeCallback();

        if ($callback === null) {
            return true;
        }

        if (is_bool($callback)) {
            return $callback;
        }

        return (bool) $callback();
    }
}
