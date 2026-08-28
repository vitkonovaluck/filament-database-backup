<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\DTOs;

final readonly class BackupRestoreFileDTO
{
    public function __construct(
        public string $absolutePath,
        public bool $isTemporary,
    ) {}
}
