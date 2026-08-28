<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Microcode\FilamentDatabaseBackup\Enums\BackupDatabaseFamily;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\Models\Backup;

final class RestoreDatabaseBackupAction
{
    public function __construct(
        private readonly ResolveBackupConnectionConfigAction $resolveConnection,
        private readonly ResolveBackupCliBinaryAction $resolveBinary,
        private readonly RunBackupCliCommandAction $runCli,
        private readonly AssertBackupFileAccessAction $assertAccess,
        private readonly MaterializeBackupFileForRestoreAction $materializeFile,
    ) {}

    public function execute(Backup $backup): void
    {
        $this->assertAccess->execute($backup, authorize: false);

        $source = $this->materializeFile->execute($backup);

        try {
            $sql = file_get_contents($source->absolutePath);

            if ($sql === false) {
                throw BackupException::restoreFailed('Unable to read backup file.');
            }

            $connection = $this->resolveConnection->execute();
            $binary = $this->resolveBinary->execute($connection->family, 'restore');

            [$command, $env] = match ($connection->family) {
                BackupDatabaseFamily::MySQL => [
                    [
                        $binary,
                        '--host='.$connection->host,
                        '--port='.(string) $connection->port,
                        '--user='.$connection->username,
                        $connection->database,
                    ],
                    ['MYSQL_PWD' => $connection->password],
                ],
                BackupDatabaseFamily::PostgreSQL => [
                    [
                        $binary,
                        '--host='.$connection->host,
                        '--port='.(string) $connection->port,
                        '--username='.$connection->username,
                        '--no-password',
                        '--dbname='.$connection->database,
                    ],
                    ['PGPASSWORD' => $connection->password],
                ],
            };

            try {
                $this->runCli->execute($command, $env, $sql);
            } catch (BackupException $exception) {
                throw BackupException::restoreFailed($exception->getMessage());
            }
        } finally {
            if ($source->isTemporary && is_file($source->absolutePath)) {
                @unlink($source->absolutePath);
            }
        }
    }
}
