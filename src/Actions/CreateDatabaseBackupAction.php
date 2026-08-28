<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Closure;
use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\DTOs\BackupConnectionConfigDTO;
use Microcode\FilamentDatabaseBackup\Enums\BackupDatabaseFamily;
use Microcode\FilamentDatabaseBackup\Enums\BackupType;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Microcode\FilamentDatabaseBackup\Support\BackupPath;
use Throwable;

final class CreateDatabaseBackupAction
{
    public function __construct(
        private readonly ResolveBackupConnectionConfigAction $resolveConnection,
        private readonly ResolveBackupCliBinaryAction $resolveBinary,
        private readonly RunBackupCliCommandAction $runCli,
        private readonly BackupDestinationResolverInterface $destination,
        private readonly PruneLocalBackupCopiesAction $pruneLocalCopies,
        private readonly CleanupExpiredBackupsAction $cleanupExpired,
    ) {}

    /**
     * @param  Closure(string $absolutePath): void|null  $dumpCallback
     */
    public function execute(
        string $name,
        ?int $backupScheduleId = null,
        ?Closure $dumpCallback = null,
    ): Backup {
        $slug = BackupPath::slugifyName($name);
        $relativePath = BackupPath::relativePath($slug);
        $localDiskName = $this->destination->localDisk();
        $localDisk = Storage::disk($localDiskName);

        $localDisk->makeDirectory(BackupPath::pathPrefix());

        $absolutePath = $localDisk->path($relativePath);

        if ($dumpCallback !== null) {
            $dumpCallback($absolutePath);
        } else {
            $this->runDump($absolutePath);
        }

        if (! is_file($absolutePath)) {
            throw BackupException::dumpFailed('Dump file was not created.');
        }

        $size = (int) filesize($absolutePath);
        $catalogDiskName = $this->destination->catalogDisk();

        if ($this->destination->usesRemote()) {
            $this->uploadToRemote($absolutePath, $relativePath, $catalogDiskName);
            $this->pruneLocalCopies->execute($relativePath);
        }

        $backup = Backup::query()->create([
            'backup_schedule_id' => $backupScheduleId,
            'name' => $slug,
            'disk' => $catalogDiskName,
            'path' => $relativePath,
            'size' => $size,
            'type' => BackupType::Database,
        ]);

        $this->cleanupExpired->execute();

        return $backup;
    }

    private function uploadToRemote(string $absolutePath, string $relativePath, string $diskName): void
    {
        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            throw BackupException::s3UploadFailed('Unable to open dump file for upload.');
        }

        try {
            $written = Storage::disk($diskName)->put($relativePath, $stream);

            if ($written !== true) {
                throw BackupException::s3UploadFailed('Remote storage rejected the backup upload.');
            }
        } catch (BackupException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw BackupException::s3UploadFailed($exception->getMessage());
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function runDump(string $absolutePath): void
    {
        $connection = $this->resolveConnection->execute();
        $binary = $this->resolveBinary->execute($connection->family, 'dump');

        [$command, $env] = match ($connection->family) {
            BackupDatabaseFamily::MySQL => $this->mysqlDumpCommand($binary, $connection),
            BackupDatabaseFamily::PostgreSQL => $this->pgsqlDumpCommand($binary, $connection),
        };

        $this->runCli->execute($command, $env, null, $absolutePath);
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function mysqlDumpCommand(string $binary, BackupConnectionConfigDTO $connection): array
    {
        $command = array_merge(
            [
                $binary,
                '--host='.$connection->host,
                '--port='.(string) $connection->port,
                '--user='.$connection->username,
            ],
            $connection->dumpFlags,
            [$connection->database],
        );

        return [$command, ['MYSQL_PWD' => $connection->password]];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function pgsqlDumpCommand(string $binary, BackupConnectionConfigDTO $connection): array
    {
        $command = array_merge(
            [
                $binary,
                '--host='.$connection->host,
                '--port='.(string) $connection->port,
                '--username='.$connection->username,
                '--no-password',
                '--format=plain',
            ],
            $connection->dumpFlags,
            [$connection->database],
        );

        return [$command, ['PGPASSWORD' => $connection->password]];
    }
}
