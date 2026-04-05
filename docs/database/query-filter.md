# QueryFilter Standard

## Purpose

QueryFilter is the SQL compiler for Veloquentfilter expressions.
It extends RuleEngine tokenization and linting concepts, then compiles conditions into Eloquent query builder clauses.

This page is the canonical standard for QueryFilter behavior.

## Public API

- `QueryFilter::for(Builder $query, array $allowedFields): static`
- `withQueryFieldAdapter(QueryFieldAdapter $adapter): static`
- `withRelationJoinResolver(RelationJoinResolver $resolver): static`
- `lint(?string $filter, bool $inMemory = false): void`
- `run(string $filter, array $context = []): Builder`

## Mode Standards

### SQL mode (`lint(..., false)` and `run(...)`)

SQL mode is the default for QueryFilter.
It enforces SQL-safe grammar and compiles to query builder calls.
The parser also accepts `&&` and `||` as aliases for `AND` and `OR`; these are normalized before validation.

### In-memory lint mode (`lint(..., true)`)

Delegates lint validation to RuleEngine in-memory grammar.
Use this mode for rule types that are evaluated in memory.

## SQL Grammar Standard

### Scalar token classes

For scalar comparisons, either side may be one of:

- `FIELD`
- `SYSVAR`
- `VALUE`
- `DATE_FUNC`

### Reversible operators

The reversible operator set is:

- `=`
- `!=`
- `>`
- `<`
- `>=`
- `<=`

These operators support symmetric operand order in SQL mode.

### Non-reversible scalar operators

`like` and `not like` are not reversible in SQL mode.
Standard rule for these operators:

- Left side must be `FIELD`
- Right side must not be `FIELD`

### List operators

For `in` and `not in` in SQL mode:

- Left side must be `FIELD`
- Right side must be a parenthesized list of scalar values

### JSON operators

For `?=` and `?&` in SQL mode:

- Left side must be `FIELD` or `SYSVAR`
- Left side must resolve to a JSON-path capable field (for example using `->` notation)
- Right side must be a scalar token

## SQL Normalization Standard

For reversible operators, QueryFilter normalizes operands to valid SQL builder calls.

### Case matrix

- `FIELD op FIELD`
  - Compiles to `whereColumn` or `orWhereColumn`
- `FIELD op SCALAR`
  - Compiles to `where(field, op, value)`
- `SCALAR op FIELD`
  - Flips to `FIELD op SCALAR`
  - Inverts ordered operators:
    - `>` becomes `<`
    - `<` becomes `>`
    - `>=` becomes `<=`
    - `<=` becomes `>=`
- `SCALAR op SCALAR`
  - Compiles to bound literal SQL comparison using `whereRaw('? op ?', [...])`

This includes expressions such as:

- `@request.auth.id = id`
- `5 > score`
- `id = updated_at`
- `@request.body.user = @request.auth.id`

## Context and System Variable Standard

`SYSVAR` values are resolved from context with:

- path stripping: `@request.auth.id` -> `request.auth.id`
- retrieval: `data_get(context, path)`

If reference resolution is disabled, system variables remain raw names.

## Adapter Standard

QueryFilter supports field adapters through `QueryFieldAdapter`.

### Built-in adapters

- `RelationJoinAdapter`
  - Handles relation dot paths such as `author.verified`
  - Resolves joined column paths via `RelationJoinResolver`
- `JsonFieldAdapter`
  - Handles JSON paths such as `meta->theme`
  - Applies JSON contains and has-key operations

## Null and Binding Standard

- `field = null` compiles to `whereNull(field)`
- `field != null` compiles to `whereNotNull(field)`
- `field is null` compiles to `whereNull(field)`
- `field is not null` compiles to `whereNotNull(field)`
- All scalar-to-scalar SQL comparisons use positional bindings, not interpolated values

## Error Standard

Invalid grammar or unsupported operand shape throws `InvalidRuleExpressionException`.

## Canonical Examples

```text
id = @request.auth.id
@request.auth.id = id
5 > score
id = updated_at
@request.body.user = @request.auth.id
status like "%active%"
id in (1, 2, 3)
meta->tags ?= "laravel"
meta->settings ?& "theme"
```

## Related Standards

- RuleEngine in-memory standard: `docs/rule-engine/rule-engine.md`
- API rule usage context: `docs/api-rules.md`
