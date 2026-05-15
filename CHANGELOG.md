# Changelog

All notable changes to Veloquent will be documented in this file.

## [2.3.0] - 2026-05-15

### Added
- **Hooks System**:
    - Lifecycle interception system using Laravel Pipelines.
    - Support for `before` (transactional) and `after` (isolated) hooks.
    - Automatic loading of `hooks.php` from the application root.
    - Human-readable event aliasing (e.g. `record.create`, `auth.login`).
    - Dedicated `Hooks` facade with full PHPDoc support.
    - `HookAbortException` for graceful operation halting.
    - Integration across all core actions (Records & Auth).
- **Documentation**:
    - New `docs/the-basics/hooks.md` guide.

### Fixed
- Improved exception handling in actions to ensure file cleanup on failure.
- Silenced and logged exceptions in "after" hooks to prevent side-effect failures.
- Superusers now bypass view rules in the realtime system.
- Fixed an issue with an unresponsive record picker modal in the admin panel.

## [2.2.0] - 2026-05-07

### Added
- **Rule Engine Hardening**:
    - Support for JSON operators: `?=` (CONTAINS) and `?&` (HASKEY) for both in-memory and SQL evaluation.
    - Support for cross-collection lookups using the `@collection.[collection].[field]` syntax.
    - Boolean negation support via the `!` (NOT) operator.
    - Optimized SQL translation using nested `EXISTS` subqueries for cross-collection JSON checks.
    - Unified prefix standard using `@` for all system variables and collection lookups.
- **Documentation Revamp**:
    - Rewrite of `api-rules.md` to be more end-user friendly, including cookbooks and function reference tables.
    - Updated `rule-engine.md` and `query-filter.md` with new grammar and technical standards.

### Changed
- Upgraded `kevintherm/exprc` dependency to `^0.0.3` for improved parsing stability and extensibility.

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
