# Changelog

All notable changes to Veloquent will be documented in this file.

## [2.6.5] - 2026-06-04

### Fixed
- **Watcher Pipeline Execution**:
    - Ensured watcher agents are executed in deterministic chronological order by sorting pivot records by the `created_at` timestamp.

### Documentation
- **AI & Watchers**:
    - Added comprehensive documentation for watcher agents.
    - Clarified the usage of chat API rule context variables.

## [2.6.4] - 2026-06-04

### Added
- **Record Validation**:
    - Implemented `RecordRelationValidator` to enforce watcher type constraints, ensuring only agent records are assigned to the watcher relationship.

## [2.6.3] - 2026-06-04

### Fixed
- **AI Streaming & JSON**:
    - Fixed list-based JSON schemas and blocked streaming for JSON output type agents.
- **Self-Referential Collections**:
    - Resolved `@self` referential failures on the agents collection by implementing self-referential collection ID resolution.

### Changed
- **AI Observability & Mapping**:
    - Improved AI watcher logging and schema mapping.

## [2.6.2] - 2026-06-04

### Added
- **AI Security**:
    - Implemented malicious prompt detection and blocking via configurable watcher agents.

## [2.6.1] - 2026-06-04

### Changed
- **Architecture Decoupling**:
    - Refactored core domains to introduce interface contracts and default concrete implementations for services, enabling clean dependency injection and extensibility.

## [2.6.0] - 2026-06-03

### Added
- **AI & Agents Infrastructure**:
    - Implemented AI settings and agent configuration infrastructure.
    - Converted the "agents" collection from a hardcoded system collection to a fully dynamic user-definable collection type.

### Documentation
- **AI Features**:
    - Added documentation for the AI & Agents feature and updated documentation for collection types.

## [2.5.0] - 2026-05-16

### Added
- **Many-to-Many Relationships**:
    - New `RelationMany` field type for bidirectional associations.
    - Support for custom pivot fields to store metadata on relationships.
    - Automatic management of physical pivot tables including creation, renaming, and type synchronization.
    - Enhanced validation for pivot fields and reserved naming conventions.
    - Integrated UI in the collection builder for configuring many-to-many relations.
- **Pipeline Architecture**:
    - Refactored schema management into a modular Pipeline system.
    - Improved atomicity of schema changes with automated recovery paths.
    - Structured error reporting for schema corruption and synchronization failures.

## [2.4.1] - 2026-05-15

### Fixed
- **Schema Transfer**:
    - Fixed an issue where cross-collection relations were broken during import due to missing ID mapping.
    - Added automated ID remapping pass for all relation fields during schema restore.
    - Improved collision detection for system collection imports.

## [2.4.0] - 2026-05-15

### Added
- **Single-Tenant Mode**:
    - Introduced `tenancy_enabled` configuration to disable multi-tenancy architecture.
    - Added a "virtual tenant" mechanism to redirect operations to the landlord database.
    - Added `ExtractTenantCommand` to provide a migration path from single-tenant to multi-tenant.
    - Updated `InstallCommand` to intelligently skip tenant setup when multi-tenancy is disabled.
- **Realtime System**:
    - Added support for the `expand` parameter in realtime subscriptions to resolve related records during broadcast.
- **Environment Management**:
    - Standardized `.env.example` structure for core and skeleton projects.

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
