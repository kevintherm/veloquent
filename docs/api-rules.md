# API Rules & Access Control

Velo provides powerful, expression-based access control to secure your data at the collection level. You can define rules for various actions, ensuring that only authorized users or requests can access or modify your records.

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

- **SQL Evaluation**: When rules are translated to SQL (e.g., for `list` or `view`), they must strictly follow the `field operator value` format (e.g., `status = "active"`). Using `value operator field` (e.g., `"active" = status`) is not supported for SQL-based filtering.
- **Memory Evaluation**: In-memory evaluation allows for more complex logic, including the use of `@` prefixed variables like `@request.body`, `@request.auth`, and `@request.query`.

### Contextual Variables

The behavior of bare variables (variables without a prefix) depends on the action being performed:

- **On Create**: Bare variables (e.g., `name`) refer to the incoming payload in `@request.body`.
- **On Update**: Bare variables refer to the **existing** record's values, while `@request.body` explicitly refers to the incoming payload. This allows you to compare current and new values (e.g., `@request.body.status != status`).

### Common Operators

Velo supports a wide range of operators for building rules:

| Operator | Description |
|---|---|
| `=`, `!=` | Equals, Does not equal. |
| `>`, `<` | Greater than, Less than. |
| `>=`, `<=` | Greater than or equal, Less than or equal. |
| `&&`, `||` | Logical AND, Logical OR. |
| `like`, `not like` | SQL LIKE pattern matching (e.g., `name like "%john%"`). |
| `in`, `not in` | Check if a value exists in a list (e.g., `status in ("active", "pending")`). |
| `is null`, `is not null` | Check for null values. |

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

## Default Behavior

If no rule is defined for an action, the default behavior is to **deny** access (except for superusers, who have full access). To allow public access, you can set a rule to `true`.

## Next Steps

After securing your data with rules, you're ready to start using the [Records API](records-api.md) to interact with your data.
