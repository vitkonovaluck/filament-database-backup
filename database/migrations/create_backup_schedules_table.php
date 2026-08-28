<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('database-backup.tables.backup_schedules', 'backup_schedules');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('frequency_type');
            $table->unsignedInteger('frequency_value')->default(1);
            $table->time('scheduled_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('retention_days')->default(30);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('database-backup.tables.backup_schedules', 'backup_schedules'));
    }
};
