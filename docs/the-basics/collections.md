# Collections & Fields

Collections are the core building blocks of your Veloquentapplication. They represent the data structures (tables) in your database and define the schema for your records.

## Collection Types

Veloquentsupports two main types of collections:
- **Base Collections**: Standard tables for storing any kind of data.
- **Auth Collections**: Specialized collections for user authentication. These collections include built-in fields for managing users and support standard and OAuth login flows.

## Fields

Each collection is made up of fields that define the structure of your records. Veloquentsupports a variety of rich field types:

| Type | Description |
|---|---|
| `text` | Single line string (max 255 chars). |
| `longtext` | Multi-line text for longer content. |
| `richtext` | Same as longtext. Used to provide rich text editor for the admin panel. |
| `number` | Floating point or integer numeric value. |
| `boolean` | True/False value. |
| `datetime` | Date and time value (Always stored and returned in UTC). |
| `date` | Date-only value (YYYY-MM-DD). |
| `url` | Validated URL. |
| `json` | Arbitrary JSON data. |
| `file` | Upload metadata (single or multiple files) with optional protected access. |
| `relation` | Link to a single record in another collection (one-to-many). |
| `relation_many` | Bidirectional link between multiple records (many-to-many). |
| `select` | Dropdown selection with predefined options. |

### File Fields

File fields support upload constraints and access protection.

| Property | Type | Description |
|---|---|---|
| `multiple` | boolean | When `true`, the field accepts an array of files. |
| `min` | integer\|null | Minimum file count for validation. |
| `max` | integer\|null | Maximum file count for validation. |
| `max_size_kb` | integer\|null | Max file size per item in KB. |
| `allowed_mime_types` | array | MIME allow-list (example: `image/png`, `application/pdf`). |
| `protected` | boolean | When `true`, file URLs are served through an authenticated API proxy route. |

#### Important

- File fields do not persist a `default` value in collection metadata.
- If a `default` value is sent for a file field, it is ignored.

## Managing Collections

You can create, update, and delete collections via the Veloquentdashboard or through the REST API. When you create or update a collection, Veloquentautomatically manages the underlying database schema for you, including:
- Creating or renaming tables.
- Adding, renaming, or deleting columns.
- Managing pivot tables for many-to-many relationships.
- Synchronizing unique and non-unique indexes.

### Relation Fields

Relation fields allow you to create powerful connections between your data. 

#### One-to-Many (`relation`)

Used to link a record to exactly one record in a target collection. 

- **Target Collection**: The collection to link to.
- **Cascade on Delete**: Toggle whether to automatically delete related records when a record is deleted.

#### Many-to-Many (`relation_many`)

Used when multiple records in one collection can be linked to multiple records in another (e.g., *Posts* and *Tags*).

- **Target Collection**: The collection to link to.
- **Pivot Fields**: Optional fields stored on the relationship itself. For example, in a *Users* to *Projects* relation, a pivot field named `role` could store the user's role in that specific project.

> [!NOTE]
> Veloquent handles the creation and maintenance of the physical pivot table automatically. If you rename a `relation_many` field or update its pivot fields, Veloquent will migrate the data and update the schema without data loss.

## Next Steps

Once you've defined your collections and fields, the next step is to secure your data with [API Rules](../security/api-rules.md).
