# API Rules & Access Control

Veloquent provides powerful, expression-based access control to secure your data at the collection level. You can define rules for various actions, ensuring that only authorized users or requests can access or modify your records.

## Action Rules

Each collection can have rules for the following actions:
- **`list`**: Controls which records are returned in a paginated list.
- **`view`**: Controls whether a single record can be retrieved.
- **`create`**: Controls who can create new records in the collection.
- **`update`**: Controls who can modify existing records.
- **`delete`**: Controls who can delete records.
- **`manage`**: (Auth collections only) Controls direct updates to sensitive fields like `email` or `password`.

## Rule Expressions

Rules are written in a custom, human-readable expression language. For example:
- `active = true`: Only records where the `active` field is true are visible.
- `@request.auth.id = creator_id`: Only the user who created the record can access it.
- `@request.auth.isAdmin = true`: Only admins can perform the action.

### Evaluation Constraints

- **SQL Evaluation**: SQL mode supports symmetric scalar comparisons for reversible operators (`=`, `!=`, `>`, `<`, `>=`, `<=`), including `field op value`, `value op field`, `field op field`, and `@request op @request`. Non-reversible operators (`like`, `not like`) require a field on the left side. See `docs/rule-engine/query-filter.md`.
- **Memory Evaluation**: In-memory evaluation supports symmetric field and `@` variable comparisons and broader context resolution with `@request.body`, `@request.auth`, and `@request.query`. See `docs/rule-engine/rule-engine.md`.

### Contextual Variables

The behavior of bare variables (variables without a prefix) depends on the action being performed:

- **On Create**: Bare variables (e.g., `name`) refer to the incoming payload in `@request.body`.
- **On Update**: Bare variables refer to the **existing** record's values, while `@request.body` explicitly refers to the incoming payload. This allows you to compare current and new values (e.g., `@request.body.status != status`).

### Common Operators

Veloquentsupports a wide range of operators for building rules:

| Operator | Description |
|---|---|
| `=`, `!=` | Equals, Does not equal. |
| `>`, `<` | Greater than, Less than. |
| `>=`, `<=` | Greater than or equal, Less than or equal. |
| `&&`, `||` | Logical AND, Logical OR. |
| `like`, `not like` | SQL LIKE pattern matching (e.g., `name like "%john%"`). |
| `in`, `not in` | Check if a value exists in a list (e.g., `status in ("active", "pending")`). |
| `is null`, `is not null` | Check for null values. |

### Relation ID comparisons

When comparing related record IDs, use an explicit ID path on the related object. For example:

```text
user = @request.auth.id && (parent_comment = null || post = parent_comment.post.id)
```

In runtime rule context, a path such as `parent_comment.post` may resolve to a hydrated related object or array, so using `.id` is required when matching scalar relation IDs.

### System References (`@request`)

Rules can reference the current request context using the `@request` prefix:

| Reference | Description |
|---|---|
| `@request.auth.*` | Access fields of the authenticated user (e.g., `@request.auth.id`, `@request.auth.email`). |
| `@request.body.*` | Access fields from the incoming request body (useful for `create` and `update` rules). |
| `@request.query.*` | Access query parameters from the request. |

### Relation Joins

You can access fields of related records using dot notation. For example, if a `posts` collection has a `userId` relation field pointing to `users`, you can write:
- `userId.active = true`: Only posts whose user is active are visible.

When a related record is expanded in API output, the foreign key remains at the top level and the expanded payload appears under `expand`.
If the expanded record is blocked by the target collection's `view` rule or the related record cannot be found, the `expand` entry is explicitly `null`.

## Default Behavior

If no rule is defined for an action, the default behavior is to **deny** access (except for superusers, who have full access). To allow public access, you can set a rule to `true`.

---

## Error Handling

Standardized API error responses:

```json
{
  "message": "Validation error",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### Critical Errors

Some errors include an `error_type` for programmatic handling:

- **`SCHEMA_CORRUPT`** (`409 Conflict`): DB table doesn't match metadata.
  ```json
  {
    "message": "The collection schema is corrupt.",
    "error_type": "SCHEMA_CORRUPT",
    "activity": "Read",
    "collection_id": "01JAB..."
  }
  ```

---

## Next Steps

After securing your data with rules, you're ready to start using the [Records API](../the-basics/records.md) to interact with your data.
