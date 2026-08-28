<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Support\BackupPath;

final class PruneLocalBackupCopiesAction
{
    public function __construct(
        private readonly BackupDestinationResolverInterface $destination,
    ) {}

    public function execute(string $keepRelativePath): void
    {
        $disk = Storage::disk($this->destination->localDisk());
        $prefix = BackupPath::pathPrefix();

        if (! $disk->exists($prefix)) {
            return;
        }

        foreach ($disk->files($prefix) as $path) {
            if (! str_ends_with(strtolower($path), '.sql')) {
                continue;
            }

            if ($path === $keepRelativePath) {
                continue;
            }

            $disk->delete($path);
        }
    }
}
