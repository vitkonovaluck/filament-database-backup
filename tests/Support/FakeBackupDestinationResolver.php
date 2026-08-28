<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Support;

use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;

final class FakeBackupDestinationResolver implements BackupDestinationResolverInterface
{
    public function __construct(
        private readonly bool $remote = false,
        private readonly string $localDisk = 'backups',
        private readonly string $remoteDiskName = 's3',
    ) {}

    public function localDisk(): string
    {
        return $this->localDisk;
    }

    public function catalogDisk(): string
    {
        return $this->remote ? $this->remoteDiskName : $this->localDisk;
    }

    public function remoteDisk(): ?string
    {
        return $this->remote ? $this->remoteDiskName : null;
    }

    public function usesRemote(): bool
    {
        return $this->remote;
    }

    /**
     * @return list<string>
     */
    public function allowedDisks(): array
    {
        $disks = [$this->localDisk];

        if ($this->remote) {
            $disks[] = $this->remoteDiskName;
        }

        return array_values(array_unique($disks));
    }
}
