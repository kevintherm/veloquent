# Collections & Fields

Collections are the core building blocks of your Velo application. They represent the data structures (tables) in your database and define the schema for your records.

## Collection Types

Velo supports two main types of collections:
- **Base Collections**: Standard tables for storing any kind of data.
- **Auth Collections**: Specialized collections for user authentication. These collections include built-in fields for managing users and support standard and OAuth login flows.

### System Protection

Collections can be marked as `is_system`. These collections have additional protection:
- **Locked Deletion**: Cannot be deleted via the standard API.
- **Restricted Modification**: Certain core fields cannot be renamed or dropped to ensure application stability.
- **Auth Lock**: The default authentication collection is always a system collection.

## Fields

Each collection is made up of fields that define the structure of your records. Velo supports a variety of rich field types:

| Type | Description |
|---|---|
| `text` | Single line string (max 255 chars). |
| `longtext` | Multi-line text for longer content. |
| `number` | Floating point or integer numeric value. |
| `boolean` | True/False value. |
| `datetime` | Date and time value. |
| `email` | Validated email address. |
| `url` | Validated URL. |
| `json` | Arbitrary JSON data. |
| `relation` | Link to a record in another collection. |

## Managing Collections

You can create, update, and delete collections via the Velo dashboard or through the REST API. When you create or update a collection, Velo automatically manages the underlying database schema for you, including:
- Creating or renaming tables.
- Adding, renaming, or deleting columns.
- Synchronizing unique and non-unique indexes.

### Relation Fields

Relation fields allow you to create powerful connections between your data. When defining a relation field, you can specify:
- **Collection**: The target collection to link to.
- **Max Select**: The maximum number of records that can be linked.
- **Cascade on Delete**: Toggle whether to automatically delete related records when a record is deleted.

### Performance & Caching

Velo uses an internal caching layer for collection metadata to minimize database overhead:
- **`findByIdCached` / `findByNameCached`**: Models are cached based on the `velo.collection_cache_ttl` configuration.
- **Cache Invalidation**: Caches are automatically cleared when a collection is updated or renamed.

## Next Steps

Once you've defined your collections and fields, the next step is to secure your data with [API Rules](../security/api-rules.md).
