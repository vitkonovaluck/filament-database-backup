# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- Optional S3 storage via `microcode/filament-integrations` AWS credentials
- Global backup retention on `FilamentDatabaseBackupPlugin::retentionDays()`
- Keep only the latest dump file on the local disk when S3 is enabled

### Changed

- PHP namespace is `Microcode\FilamentDatabaseBackup`
- Restore works from S3 as well as local disks
- Expired backup cleanup now covers manual backups using the plugin retention default

## [1.0.0] - 2026-08-28

- Initial release: MySQL/MariaDB/PostgreSQL backup, restore, and scheduling
