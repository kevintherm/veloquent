# Rule Engine Standard

## Purpose

The RuleEngine is the in-memory evaluator for Veloquentfilter expressions.
It validates and evaluates rule strings against a PHP array context.

This page is the canonical standard for RuleEngine behavior.

## Public API

- `RuleEngine::make(array $allowedFields = [])`
- `withAllowedFields(array $allowedFields): static`
- `addFieldResolverAdapter(FieldResolverAdapter $adapter): static`
- `lint(string $filter, bool $inMemory = true): void`
- `evaluate(string $filter, array $context = []): bool`

## Expression Grammar

RuleEngine uses a recursive descent parser over tokenized input.

```text
expression     := orExpr
orExpr         := andExpr ( OR andExpr )*
andExpr        := primary ( AND primary )*
primary        := '(' expression ')' | condition
condition      := lhs OP rhs
lhs            := FIELD | SYSVAR
rhs            := scalar | list
scalar         := VALUE | DATE_FUNC | FIELD | SYSVAR
list           := '(' scalar ( ',' scalar )* ')'
```

## Token Types

- `FIELD`: plain identifier, such as `status`, `author.verified`, `meta->tags`
- `SYSVAR`: `@`-prefixed variable, such as `@request.auth.id`
- `VALUE`: literal (`string`, number, `true`, `false`, `null`)
- `DATE_FUNC`: date helper call (`today()`, `daysago(7)`, etc.)
- `OP`: comparison operator
- `AND`, `OR`, `LPAREN`, `RPAREN`, `COMMA`

## Supported Operators

- Scalar operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `like`, `not like`
- List operators: `in`, `not in`
- JSON operators: `?=`, `?&`
- Logical operators: `&&`, `||`

Note: `&&` and `||` are accepted syntax and are normalized to `AND` and `OR` before parsing.

Null checks are expressed as:

- `field = null`
- `field != null`
- `field is null`
- `field is not null`

## Operand Standards

### Symmetry

RuleEngine supports symmetric field and system-variable comparisons:

- `field op @sysvar`
- `@sysvar op field`
- `field op field`
- `@sysvar op @sysvar`

Literal values are standard on the right side.
Left side is restricted to `FIELD` or `SYSVAR` in RuleEngine grammar.

### Context Resolution

- `FIELD` resolves from context via `data_get(context, fieldPath)`
- `SYSVAR` resolves from context via `data_get(context, substr(name, 1))`
- Missing values resolve to `null`
- When comparing relation IDs in an in-memory rule, use explicit ID paths such as `post = parent_comment.post.id` instead of `post = parent_comment.post`.

### Adapter Resolution Order

When adapters are registered, RuleEngine checks adapters first, then fallback resolution.

## System Variable Prefix Standard

Allowed prefixes:

- `@request.auth.`
- `@request.body.`
- `@request.param.`
- `@request.query.`

Any other `@` prefix is invalid during lint.

## Date Function Standard

Veloquentsupports dynamic date calculations in expressions. Date functions are evaluated to ISO-8601 strings during processing.

### No-argument Functions

- `now()`, `today()`
- `yesterday()`, `tomorrow()`
- `thisweek()`, `lastweek()`, `nextweek()`
- `thismonth()`, `lastmonth()`, `nextmonth()`
- `thisyear()`, `lastyear()`, `nextyear()`
- `startofday()`, `endofday()`
- `startofweek()`, `endofweek()`
- `startofmonth()`, `endofmonth()`
- `startofyear()`, `endofyear()`

### Functions requiring numeric argument

- `daysago(n)`, `daysfromnow(n)`
- `weeksago(n)`, `weeksfromnow(n)`
- `monthsago(n)`, `monthsfromnow(n)`
- `yearsago(n)`, `yearsfromnow(n)`

---

## Grammar Details

### Escaping

String literals support backslash escaping for quotes:
- `"He said \"Hello\""`
- `'It\'s working'`

### JSON Operators

JSON-specific operators (`?=`, `?&`) are supported in both in-memory and SQL modes:
- `meta->tags ?= "php"` (Check if JSON array contains value)
- `meta->tags ?& ("php", "laravel")` (Check if JSON array contains all values)

> [!NOTE]
> When using `?=` and `?&` in SQL mode (via `QueryFilter`), the underlying database must support JSON path operations.

### Functions without arguments

- `now()`, `today()`, `yesterday()`, `tomorrow()`
- `thisweek()`, `lastweek()`, `nextweek()`
- `thismonth()`, `lastmonth()`, `nextmonth()`
- `thisyear()`, `lastyear()`, `nextyear()`
- `startofday()`, `endofday()`
- `startofweek()`, `endofweek()`
- `startofmonth()`, `endofmonth()`
- `startofyear()`, `endofyear()`

## Equality and Ordering Semantics

- Carbon operands are compared by Unix timestamp
- If either side is `null`, `=` uses strict null equality and `!=` is its inverse
- `like` and `not like` use SQL-like wildcard semantics (`%`, `_`)
- `?=` checks JSON array containment
- `?&` checks JSON object key existence

## Lint vs Evaluate

### Lint

- Validates field names against `allowedFields`
- Validates system-variable prefixes
- Validates grammar only
- Throws `InvalidRuleExpressionException` on invalid expressions

### Evaluate

- Tokenizes with unknown fields allowed
- Resolves values against runtime context
- Returns `bool`

## Canonical Examples

```text
status = "active"
@request.auth.id = owner_id
owner_id = @request.auth.id
@request.body.id = @request.auth.id
role in ("admin", "editor")
created_at >= daysago(30)
settings ?& "theme"
metadata->tags ?= "php"
```

## Related Standards

- QueryFilter SQL standard: `docs/rule-engine/query-filter.md`
- API rule usage context: `docs/api-rules.md`
