# API Rules & Access Control

Veloquent provides powerful, expression-based access control to secure your data. You can define rules for various actions, ensuring that only authorized users or requests can access or modify your records.

This guide is designed to help you quickly understand and write rules for your collections without needing deep technical knowledge of the underlying engine.

---

## Action Rules

Each collection can have specific rules defined for the following actions:

- **`list`**: Controls which records are returned when fetching multiple records via the API.
- **`view`**: Controls whether a specific single record can be retrieved.
- **`create`**: Controls who can create new records in the collection.
- **`update`**: Controls who can modify existing records.
- **`delete`**: Controls who can delete records.
- **`manage`**: (Auth collections only) Controls direct updates to sensitive authentication fields like `email` or `password`.

If an action's rule is `null` (or undefined), the default behavior is to **deny** access. To make an action fully public, set the rule to an empty string (`""`).

---

## Contextual Variables (`@request`)

Rules can dynamically reference the current context of the API request using the `@request` prefix.

| Variable | Description | Example Use Case |
|---|---|---|
| `@request.auth.*` | Access fields of the currently authenticated user. | `@request.auth.id = owner_id` |
| `@request.body.*` | Access data from the incoming request payload. | `@request.body.status = "published"` |
| `@request.query.*` | Access URL query string parameters (e.g., `?token=...`). | `@request.query.token = "x"` |
| `@request.param.*` | Access named route parameters (e.g., `{id}`). | `@request.param.id = x` |

### "Bare" Variables vs `@request.body`

When you write a field name without any prefix (e.g., `status`), its meaning changes slightly depending on the action:

- **On Create**: Bare variables refer to the **incoming data** you are trying to save (equivalent to `@request.body`).
- **On Update**: Bare variables refer to the **existing** record in the database. To access the incoming changes, you *must* use `@request.body`.
  - *Example*: `@request.body.status != status` (Only allow the update if the status is actually changing).

---

## Cross-Collection Lookups (`@collection`)

Sometimes, determining access requires checking data in a completely different collection. You can do this using the `@collection` prefix, which acts as a subquery.

**Syntax**: `@collection.[collection_name].[field] = value`

**Examples**:
- Only allow users to comment if they have an active subscription:
  `@collection.subscriptions.user_id = @request.auth.id`
- Check if the current user has the "admin" role in a separate `users` collection:
  `@collection.users.id = @request.auth.id && @collection.users.roles ?= "admin"`

---

## Supported Operators

Veloquent supports a wide range of operators to build complex logic:

| Operator | Description | Example |
|---|---|---|
| `=`, `!=` | Equals, Does not equal | `status = "active"` |
| `>`, `<` | Greater than, Less than | `age > 18` |
| `>=`, `<=` | Greater than or equal, Less than or equal | `score >= 100` |
| `&&`, `||` | Logical AND, Logical OR | `active = true && verified = true` |
| `!` | Logical NOT (negation) | `!(tags ?= "banned")` |
| `?=`, `?&` | JSON Contains (array), JSON Has Key (object) | `tags ?= "php"` |
| `in`, `not in` | Check if value exists (or not) in a list | `role in ("admin", "editor")` |
| `like`, `not like`| String pattern matching (`%` for wildcard) | `email like "%@example.com"` |
| `is null`, `is not null` | Check if a field is empty | `deleted_at is null` |

---

## Supported Functions

You can use dynamic functions, particularly for date calculations, directly in your rules:

### Value Functions
These functions generate a dynamic value that you can compare your fields against.

| Function | Description |
|---|---|
| `now()`, `today()` | Current date/time, Current start of day. |
| `yesterday()`, `tomorrow()` | Start of yesterday, Start of tomorrow. |
| `thisweek()`, `lastweek()`, `nextweek()` | Start of the respective week. |
| `thismonth()`, `lastmonth()`, `nextmonth()` | Start of the respective month. |
| `thisyear()`, `lastyear()`, `nextyear()` | Start of the respective year. |
| `daysago(n)`, `daysfromnow(n)` | Date exactly `n` days ago / from now. |
| `weeksago(n)`, `weeksfromnow(n)` | Date exactly `n` weeks ago / from now. |
| `monthsago(n)`, `monthsfromnow(n)` | Date exactly `n` months ago / from now. |
| `yearsago(n)`, `yearsfromnow(n)` | Date exactly `n` years ago / from now. |

### Field Functions
These functions are used to wrap a field name to extract or transform its value before comparing it.

| Function | Description | Example |
|---|---|---|
| `date(field)` | Extracts the date part (YYYY-MM-DD). | `date(created_at) = today()` |
| `year(field)` | Extracts the year as an integer. | `year(created_at) = 2024` |
| `month(field)` | Extracts the month (1-12) as an integer. | `month(created_at) = 5` |
| `day(field)` | Extracts the day of the month (1-31). | `day(created_at) = 15` |
| `time(field)` | Extracts the time part (HH:MM:SS). | `time(created_at) > "12:00:00"` |

---

## Practical Examples

Here are some common rule patterns you can copy and adapt for your project:

**1. Public Read-Only Access**
```text
""
```

**2. Only the Creator Can Access / Modify**
```text
user = @request.auth.id
```

**3. Only Admins Can Access**
```text
@request.auth.role = "admin"
```

**4. Prevent Editing Specific Fields**
*(On Update Action)*
```text
@request.body.is_verified = is_verified
```
*(This ensures the incoming `is_verified` value exactly matches the existing one, preventing tampering).*

**5. Must Be Published or Authored by Current User**
```text
status = "published" || user = @request.auth.id
```

**6. Block Banned Users (Using JSON Tags)**
```text
!( @request.auth.tags ?= "banned" )
```

**7. Only authenticated user can access**
```text
@request.auth.id != null
```

---

## Deep Dives for Developers

If you want to understand exactly how the Veloquent Rule Engine evaluates these expressions or translates them into SQL queries under the hood, check out the these documentation or checkout our repository directly:
- [Rule Engine Standard](../database/rule-engine.md) (In-memory evaluation details)
- [Query Filter Standard](../database/query-filter.md) (SQL compilation details)
- [Veloquent Rule Engine](https://github.com/kevintherm/veloquent/blob/main/src/Domain/RuleEngine/RuleEngine.php)