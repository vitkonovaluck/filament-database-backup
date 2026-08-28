<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Actions;

use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Symfony\Component\Process\Process;

final class RunBackupCliCommandAction
{
    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    public function execute(
        array $command,
        array $env = [],
        ?string $input = null,
        ?string $outputPath = null,
    ): void {
        $timeout = (int) config('database-backup.process_timeout_seconds', 600);

        $process = new Process($command, null, array_merge($_ENV, $_SERVER, $env));
        $process->setTimeout($timeout);

        if ($input !== null) {
            $process->setInput($input);
        }

        if ($outputPath !== null) {
            $handle = fopen($outputPath, 'wb');

            if ($handle === false) {
                throw BackupException::dumpFailed('Unable to open output file for writing.');
            }

            try {
                $process->run(function (string $type, string $buffer) use ($handle): void {
                    if ($type === Process::OUT) {
                        fwrite($handle, $buffer);
                    }
                });
            } finally {
                fclose($handle);
            }
        } else {
            $process->run();
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            $error = $this->redactSecrets($error);

            throw BackupException::dumpFailed($error !== '' ? $error : 'Process exited with code '.$process->getExitCode());
        }
    }

    private function redactSecrets(string $message): string
    {
        return (string) preg_replace(
            '/(MYSQL_PWD|PGPASSWORD)\s*=\s*\S+/i',
            '$1=***',
            $message
        );
    }
}
