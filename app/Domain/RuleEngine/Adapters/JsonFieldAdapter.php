<?php

namespace App\Domain\RuleEngine\Adapters;

use App\Domain\RuleEngine\Contracts\FieldResolverAdapter;
use App\Domain\RuleEngine\Contracts\QueryFieldAdapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Adapter for JSON column path access using -> notation.
 *
 * Field syntax: column->path  or  column->nested->path
 *
 * Operators supported in SQL mode via Eloquent's native JSON helpers
 * (database-agnostic — Eloquent handles MySQL/SQLite/PostgreSQL differences):
 *   field->path = value          → whereJsonContains / where with ->->> cast
 *   field->path ?= value         → whereJsonContains (value in json array/object)
 *   field->path ?& key           → whereJsonLength (has key / non-empty)
 *
 * In-memory: resolves via data_get() using dot-notation equivalent.
 */
class JsonFieldAdapter implements FieldResolverAdapter, QueryFieldAdapter
{
    public function supports(string $fieldPath): bool
    {
        // Matches any field that contains '->' (JSON path separator)
        return str_contains($fieldPath, '->');
    }

    // ── In-memory (FieldResolverAdapter) ──────────────────────────────────

    public function resolveForEvaluation(string $fieldPath, array $context): mixed
    {
        // Convert "column->nested->key" to "column.nested.key" for data_get()
        $dotPath = str_replace('->', '.', $fieldPath);

        $value = data_get($context, $dotPath);

        // If the top-level column value is a JSON string, decode it first
        // then re-resolve the remaining path
        $segments = explode('.', $dotPath);
        if (count($segments) > 1) {
            $columnValue = data_get($context, $segments[0]);
            if (is_string($columnValue)) {
                $decoded = json_decode($columnValue, true);
                if (is_array($decoded)) {
                    $remainingPath = implode('.', array_slice($segments, 1));

                    return data_get($decoded, $remainingPath);
                }
            }
        }

        return $value;
    }

    // ── SQL-mode (QueryFieldAdapter) ───────────────────────────────────────

    /**
     * Resolves the JSON field to an Eloquent-compatible column reference.
     *
     * Eloquent understands "column->path" natively via Grammar::wrapJsonSelector(),
     * so we return the raw field as-is and let the builder handle it.
     *
     * Note: The returned string is used as the column arg in ->where() calls.
     * Eloquent wraps JSON selectors automatically for MySQL/SQLite/PostgreSQL.
     */
    public function resolveForQuery(string $fieldPath, Builder $query): string|Expression
    {
        // Eloquent's query grammar natively handles "column->key" JSON selectors
        // in where() / orderBy() etc., so we return the path directly.
        return $fieldPath;
    }

    /**
     * Apply a JSON contains clause using Eloquent's whereJsonContains.
     * Called by QueryFilter when it detects a ?= operator on a JSON field.
     */
    public function applyJsonContains(Builder $query, string $fieldPath, mixed $value, bool $isOr): void
    {
        $method = $isOr ? 'orWhereJsonContains' : 'whereJsonContains';
        $query->$method($fieldPath, $value);
    }

    /**
     * Apply a JSON has-key clause using Eloquent's whereJsonLength.
     * Checks that the array/object at $fieldPath contains the given key (length > 0
     * or key exists). For JSON objects, we check for specific key membership.
     *
     * Called by QueryFilter when it detects a ?& operator on a JSON field.
     */
    public function applyJsonHasKey(Builder $query, string $fieldPath, mixed $key, bool $isOr): void
    {
        // For JSON objects: use whereJsonContainsKey (Laravel 10+)
        // For JSON arrays: length check
        // We use whereJsonContains as a pragmatic cross-db solution for key membership.
        $method = $isOr ? 'orWhereJsonContainsKey' : 'whereJsonContainsKey';

        if (method_exists($query, $method)) {
            // Laravel 10+: whereJsonContainsKey('column->key')
            $query->$method("{$fieldPath}->{$key}");
        } else {
            // Fallback: length check
            $method = $isOr ? 'orWhereJsonLength' : 'whereJsonLength';
            $query->$method($fieldPath, '>', 0);
        }
    }
}
