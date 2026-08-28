<?php

declare(strict_types=1);

namespace Microcode\FilamentDatabaseBackup\Tests\Unit;

use Microcode\FilamentDatabaseBackup\Actions\ResolveBackupConnectionConfigAction;
use Microcode\FilamentDatabaseBackup\Enums\BackupDatabaseFamily;
use Microcode\FilamentDatabaseBackup\Exceptions\BackupException;
use Microcode\FilamentDatabaseBackup\Tests\TestCase;

final class ResolveBackupConnectionConfigActionTest extends TestCase
{
    public function test_maps_mysql_driver_to_mysql_family(): void
    {
        $dto = app(ResolveBackupConnectionConfigAction::class)->execute('mysql');

        $this->assertSame(BackupDatabaseFamily::MySQL, $dto->family);
        $this->assertSame('app', $dto->database);
        $this->assertContains('--single-transaction', $dto->dumpFlags);
    }

    public function test_maps_mariadb_driver_to_mysql_family(): void
    {
        $dto = app(ResolveBackupConnectionConfigAction::class)->execute('mariadb');

        $this->assertSame(BackupDatabaseFamily::MySQL, $dto->family);
    }

    public function test_maps_pgsql_driver_to_postgresql_family(): void
    {
        $dto = app(ResolveBackupConnectionConfigAction::class)->execute('pgsql');

        $this->assertSame(BackupDatabaseFamily::PostgreSQL, $dto->family);
        $this->assertContains('--no-owner', $dto->dumpFlags);
    }

    public function test_rejects_unsupported_driver(): void
    {
        $this->expectException(BackupException::class);

        app(ResolveBackupConnectionConfigAction::class)->execute('sqlite');
    }
}
