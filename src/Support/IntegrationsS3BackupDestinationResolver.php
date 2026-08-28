<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Support;

use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentIntegrations\Contracts\IntegrationSettingsServiceInterface;
use Microcode\FilamentIntegrations\Enums\DriverKey;
use Throwable;

final class IntegrationsS3BackupDestinationResolver implements BackupDestinationResolverInterface
{
    public function localDisk(): string
    {
        return BackupPath::disk();
    }

    public function catalogDisk(): string
    {
        return $this->remoteDisk() ?? $this->localDisk();
    }

    public function remoteDisk(): ?string
    {
        if (! $this->usesRemote()) {
            return null;
        }

        $configured = config('database-backup.s3.disk');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fromIntegrations = config('filament-integrations.aws.disk', 's3');

        return is_string($fromIntegrations) && $fromIntegrations !== ''
            ? $fromIntegrations
            : 's3';
    }

    public function usesRemote(): bool
    {
        if (! (bool) config('database-backup.s3.enabled', true)) {
            return false;
        }

        if (! interface_exists(IntegrationSettingsServiceInterface::class)) {
            return false;
        }

        if (! app()->bound(IntegrationSettingsServiceInterface::class)) {
            return false;
        }

        try {
            $settings = app(IntegrationSettingsServiceInterface::class);
        } catch (Throwable) {
            return false;
        }

        $key = $settings->get(DriverKey::Aws->value, 'access_key_id');
        $secret = $settings->get(DriverKey::Aws->value, 'secret_access_key');
        $region = $settings->get(DriverKey::Aws->value, 'region');
        $bucket = $settings->get(DriverKey::Aws->value, 'bucket');

        return ! empty($key) && ! empty($secret) && ! empty($region) && ! empty($bucket);
    }

    /**
     * @return list<string>
     */
    public function allowedDisks(): array
    {
        $disks = [$this->localDisk()];
        $remote = $this->remoteDisk();

        if ($remote !== null && $remote !== '') {
            $disks[] = $remote;
        }

        return array_values(array_unique($disks));
    }
}
