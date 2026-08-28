<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\DTOs;

use Microcode\FilamentDatabaseBackup\Enums\BackupDatabaseFamily;

final readonly class BackupConnectionConfigDTO
{
    /**
     * @param  array<int, string>  $dumpFlags
     */
    public function __construct(
        public BackupDatabaseFamily $family,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public array $dumpFlags,
    ) {}
}
