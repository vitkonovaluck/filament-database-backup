<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Contracts;

interface BackupDestinationResolverInterface
{
    public function localDisk(): string;

    public function catalogDisk(): string;

    public function remoteDisk(): ?string;

    public function usesRemote(): bool;

    /**
     * @return list<string>
     */
    public function allowedDisks(): array;
}
