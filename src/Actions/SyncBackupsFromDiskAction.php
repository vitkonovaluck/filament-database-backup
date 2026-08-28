<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Enums\BackupType;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Support\BackupPath;

final class SyncBackupsFromDiskAction
{
    public function __construct(
        private readonly BackupDestinationResolverInterface $destination,
    ) {}

    public function execute(): int
    {
        $diskName = $this->destination->catalogDisk();
        $disk = Storage::disk($diskName);
        $prefix = BackupPath::pathPrefix();
        $upserted = 0;

        if (! $disk->exists($prefix)) {
            return 0;
        }

        $files = $disk->files($prefix);

        foreach ($files as $path) {
            if (! str_ends_with(strtolower($path), '.sql')) {
                continue;
            }

            $basename = basename($path, '.sql');
            $name = BackupPath::slugifyName($basename);

            $existing = Backup::query()
                ->withTrashed()
                ->where('disk', $diskName)
                ->where('path', $path)
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    continue;
                }

                $size = (int) $disk->size($path);
                if ((int) $existing->size !== $size || $existing->name !== $name) {
                    $existing->update([
                        'name' => $name,
                        'size' => $size,
                        'type' => BackupType::Database,
                    ]);
                    $upserted++;
                }

                continue;
            }

            Backup::query()->create([
                'backup_schedule_id' => null,
                'name' => $name,
                'disk' => $diskName,
                'path' => $path,
                'size' => (int) $disk->size($path),
                'type' => BackupType::Database,
            ]);

            $upserted++;
        }

        return $upserted;
    }
}
