<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Microcode\FilamentDatabaseBackup\Actions\AssertBackupFileAccessAction;
use Microcode\FilamentDatabaseBackup\Contracts\BackupDestinationResolverInterface;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\Models\Backup;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BackupDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Backup $backup,
        AssertBackupFileAccessAction $assertAccess,
        BackupDestinationResolverInterface $destination,
    ): StreamedResponse {
        try {
            $assertAccess->execute($backup, authorize: true);
        } catch (BackupException $exception) {
            if ($exception->getMessage() === BackupException::unauthorized()->getMessage()) {
                throw new AccessDeniedHttpException($exception->getMessage());
            }

            throw new NotFoundHttpException($exception->getMessage());
        }

        $localDisk = Storage::disk($destination->localDisk());
        $disk = ($backup->path !== '' && $localDisk->exists($backup->path))
            ? $localDisk
            : Storage::disk($backup->disk);
        $chunkSize = (int) config('database-backup.chunk_size', 1024 * 1024);

        return response()->streamDownload(
            function () use ($disk, $backup, $chunkSize): void {
                $stream = $disk->readStream($backup->path);

                if ($stream === false) {
                    return;
                }

                while (! feof($stream)) {
                    $chunk = fread($stream, $chunkSize);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                }

                fclose($stream);
            },
            $backup->name.'.sql',
            [
                'Content-Type' => 'application/sql',
            ]
        );
    }
}
