# Changelog

All notable changes to Veloquent will be documented in this file.

## [2.1.0] - 2026-05-04

### Added
- **Select Field Type**: Support for dropdown selection with predefined options in collections.
- Comprehensive validation for select field options.

## [2.0.8] - 2026-05-04

### Fixed
- Array to string conversion error in certain contexts.

## [2.0.7] - 2026-05-04

### Changed
- Improved console command output formatting.
- Added `.gitattributes` file for better repository management.

## [2.0.5] - 2026-05-04

### Added
- Installation completion guide in the CLI.
- Log configuration validation for better error reporting.

## [2.0.1] - 2026-05-03

### Added
- New `php artisan velo:install` command to automate setup.
- Hardened multi-tenancy initialization.

### Fixed
- Tenant middleware issues.
- SQL errors when tenant failed to initialize in `TokenAuthService`.
- Vite manifest missing error in production environments.
- Tenant cache key mismatch.
- Database naming length issues with SQLite driver.

## [2.0.0] - 2026-05-02

### Changed
- **Major Architectural Shift**: Transitioned from a skeleton-based project to a package-based structure (`veloquent/core`).
- **Upgrade**: Upgraded core framework to **Laravel 13**.
- Reordered migrations for better dependency management.
- Added dedicated cache and jobs migrations.
- Updated default `admin_prefix` to an empty string.

---

For older changes, please refer to the Git commit history.
