# Schema Management & Maintenance

Veloquent providing tools for schema health, orphan table cleanup, and transferring metadata between environments.

## Health & Recovery

Veloquent ensures that your database tables stay in sync with your collection metadata.

### `GET /api/schema/corrupt`
Detects collections where the physical database table does not match the expected schema defined in the `collections` metadata.

### `POST /api/collections/{collection}/recover`
Attempts to fix a corrupted collection by reapplying the schema changes (DDL) to the database table.

## Orphan Management

Orphan tables are database tables that exist in the database but have no corresponding entry in the Veloquent `collections` table. This can happen if a collection was manually deleted from the metadata or during a failed migration.

### `GET /api/schema/orphans`
Lists all detected orphan tables.

### `DELETE /api/schema/orphans`
Drops all orphan tables from the database. **Use with caution.**

### `DELETE /api/schema/orphans/{table_name}`
Drops a specific orphan table.

---

## Schema Transfer, Backup, and Import

Veloquent provides a built-in mechanism to export and import your database schema (collections metadata) and records. This is useful for backups, migrating between environments (e.g., staging to production), or sharing project structures.

## Overview

The Schema Transfer feature is exposed via the API and is restricted to **Superusers**. It covers:

- **Metadata Export**: Exporting collection definitions, fields, API rules, and indexes.
- **Full Backup**: Exporting both metadata and all records in selected collections.
- **System Tables**: Transferring core system data like superusers, email templates, and OAuth providers.
- **Import with Conflict Resolution**: Restoring data with options to skip or overwrite existing records.

## Exporting Data

You can export data by sending a `POST` request to `/api/schema/transfer/export`.

### Options

| Field | Type | Description |
|---|---|---|
| `collections` | array | Names of user-defined collections to export. |
| `system_tables` | array | Names of system tables to export (e.g., `superusers`, `email_templates`). |
| `include_records` | boolean | Whether to include all records in the export (default: `true`). |

> [!TIP]
> To perform a **metadata-only export**, set `include_records` to `false`. This is ideal for sharing your project structure without exposing sensitive data.

## Importing Data

To import data, send a `POST` request to `/api/schema/transfer/import` with the JSON payload received from an export.

### Request Body

| Field | Type | Description |
|---|---|---|
| `payload` | array | The JSON payload from the export. |
| `conflict` | string | Conflict strategy: `skip` (default) or `overwrite`. |

## Important Considerations

### *Stale Relation Data
Relation fields store references to other records (often by ID). When importing records into a new environment, **relation fields will contain stale data** if the referenced records' IDs have changed or do not exist in the target environment. You must manually verify and update these relations after the import is complete.

### *System Collections & Transactions
Importing **system collections** (e.g., `superusers`, `collections`) does **not use database transactions**.
- If an error occurs (e.g., a SQL unique constraint violation), the process will stop immediately and throw an error.
- **Example**: If you import a `superusers` row with an email that already exists (like the default superuser email), it will cause a unique constraint error. Ensure you have handled unique fields before importing.

### User Collections & Transactions
Unlike system tables, imports for **user-defined collections** are wrapped in database transactions per table. If an error occurs during the import of a specific collection's records, the changes for that collection will be rolled back.

## API Reference

### `GET /api/schema/transfer/options`
Returns a list of available collections and allowed system tables for export.

### `POST /api/schema/transfer/export`
Generates a JSON export payload.

### `POST /api/schema/transfer/import`
Imports a JSON payload. Requires `payload` (array) and optional `conflict` (string, `skip`|`overwrite`) in the request body.
