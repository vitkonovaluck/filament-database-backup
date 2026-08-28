<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Microcode\FilamentDatabaseBackup\Http\Controllers\BackupDownloadController;

Route::get('database-backup/{backup}/download', BackupDownloadController::class)
    ->middleware(['web'])
    ->name('database-backup.download');
