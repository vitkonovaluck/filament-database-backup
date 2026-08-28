<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('database-backup.tables.backups', 'backups');
        $schedulesTable = config('database-backup.tables.backup_schedules', 'backup_schedules');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($schedulesTable): void {
            $table->id();
            $table->foreignId('backup_schedule_id')
                ->nullable()
                ->constrained($schedulesTable)
                ->nullOnDelete();
            $table->string('name');
            $table->string('disk')->default('backups');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('type')->default('database');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('database-backup.tables.backups', 'backups'));
    }
};
