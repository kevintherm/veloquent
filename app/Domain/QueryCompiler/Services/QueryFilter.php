<?php

namespace App\Domain\QueryCompiler\Services;

use App\Domain\Records\Services\RelationJoinResolver;
use App\Domain\RuleEngine\Adapters\JsonFieldAdapter;
use App\Domain\RuleEngine\Adapters\RelationJoinAdapter;
use App\Domain\RuleEngine\Contracts\QueryFieldAdapter;
use App\Domain\RuleEngine\RuleEngine;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQL query filter — extends RuleEngine to compile filter expressions
 * into Eloquent Builder where-clauses.
 *
 * Shares the tokenizer, date helpers, value casting and lint/evaluate logic
 * from RuleEngine. Only the condition application path differs: instead of
 * evaluating in-memory, conditions are compiled to Eloquent where() calls.
 *
 * LHS/RHS normalization in SQL mode:
 *   user = @request.auth.id              → ->where('user', '=', $authId)
 *
 *   @request.auth.id = user              → ->where('user', '=', $authId)
 *   5 > score                            → ->where('score', '<', 5)
 *
 *   @request.body.user = @request.auth.id→ ->whereRaw('? = ?', [$bodyUser, $authId])
 */
class QueryFilter extends RuleEngine
{
    private Builder $query;

    /** @var list<QueryFieldAdapter> */
    private array $queryFieldAdapters = [];

    private array $sqlContext = [];

    private bool $resolveReferences = false;

    // ── Factory ───────────────────────────────────────────────────────────────

    public static function for(Builder $query, array $allowedFields): static
    {
        $instance = new static;
        $instance->query = $query;
        $instance->allowedFields = $allowedFields; // sets the protected property from RuleEngine

        return $instance;
    }

    /**
     * Add a QueryFieldAdapter for SQL-mode field resolution (joins, JSON, etc.).
     */
    public function withQueryFieldAdapter(QueryFieldAdapter $adapter): static
    {
        $this->queryFieldAdapters[] = $adapter;

        return $this;
    }

    /**
     * Convenience: register a RelationJoinResolver as an adapter.
     *
     * @deprecated Use withQueryFieldAdapter(new RelationJoinAdapter($resolver)) instead.
     */
    public function withRelationJoinResolver(RelationJoinResolver $resolver): static
    {
        return $this->withQueryFieldAdapter(
            new RelationJoinAdapter($resolver)
        );
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Compile and apply a filter expression to the Eloquent query builder.
     */
    public function run(string $filter, array $context = []): Builder
    {
        $filter = trim($filter);
        if ($filter === '') {
            return $this->query;
        }

        $this->sqlContext = $context;
        $this->resolveReferences = true;

        try {
            $tokens = $this->makeTokenizer(
                allowedFields: $this->allowedFields,
                allowUnknownFields: false,
            )->tokenize($filter);

            $this->setTokens($tokens);
            $this->applyOr($this->query, false);
        } finally {
            $this->resolveReferences = false;
            $this->sqlContext = [];
        }

        return $this->query;
    }

    /**
     * Validate a filter expression.
     *
     * @param  bool  $inMemory  When true, uses RuleEngine in-memory lint (fields/@-vars on either side).
     *                          When false (default), validates SQL-mode grammar.
     */
    public function lint(?string $filter, bool $inMemory = false): void
    {
        $filter = trim($filter ?? '');
        if ($filter === '') {
            return;
        }

        if ($inMemory) {
            // In-memory mode: delegate to the parent — it uses $this->allowedFields (protected)
            parent::lint($filter);
        } else {
            // SQL mode: tokenize with field validation, then do a SQL grammar dry-run
            $tokens = $this->makeTokenizer(
                allowedFields: $this->allowedFields,
                allowUnknownFields: false,
            )->tokenize($filter);

            foreach ($tokens as $token) {
                if ($token['type'] === 'SYSVAR') {
                    $this->validateSysVar($token['value']);
                }
            }

            $this->setTokens($tokens);
            $this->sqlDryRunOr();
        }
    }

    // ── Recursive descent — SQL mode dry-run (lint) ────────────────────────

    private function sqlDryRunOr(): void
    {
        $this->sqlDryRunAnd();
        while ($this->peek('OR')) {
            $this->consume();
            $this->sqlDryRunAnd();
        }
    }

    private function sqlDryRunAnd(): void
    {
        $this->sqlDryRunPrimary();
        while ($this->peek('AND')) {
            $this->consume();
            $this->sqlDryRunPrimary();
        }
    }

    private function sqlDryRunPrimary(): void
    {
        if ($this->peek('LPAREN')) {
            $this->consume();
            $this->sqlDryRunOr();
            $this->expect('RPAREN');

            return;
        }

        $this->sqlDryRunCondition();
    }

    private function sqlDryRunCondition(): void
    {
        $leftToken = $this->consume();
        if (! in_array($leftToken['type'], ['FIELD', 'SYSVAR', 'VALUE', 'DATE_FUNC'], true)) {
            $this->invalid("Expected scalar token on left-hand side, got '{$leftToken['value']}'");
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            $this->invalid("Expected operator, got '{$opToken['value']}'");
        }

        $op = $opToken['value'];

        if ($op === 'in' || $op === 'not in') {
            if ($leftToken['type'] !== 'FIELD') {
                $this->invalid("Operator '{$op}' requires a field on the left-hand side in SQL mode");
            }
            $this->dryRunListValue();

            return;
        }

        if ($op === '?=' || $op === '?&') {
            if (! in_array($leftToken['type'], ['FIELD', 'SYSVAR'], true)) {
                $this->invalid('JSON operators require a field or @-variable on the left-hand side in SQL mode');
            }

            $rightToken = $this->consume();
            if (! in_array($rightToken['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
                $this->invalid("Expected scalar token on right-hand side, got '{$rightToken['value']}'");
            }

            return;
        }

        // Skip the RHS token (can be VALUE, DATE_FUNC, FIELD, SYSVAR)
        $rightToken = $this->consume();
        if (! in_array($rightToken['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
            $this->invalid("Expected scalar token on right-hand side, got '{$rightToken['value']}'");
        }

        if (! $this->isReversibleSqlOperator($op)) {
            if ($leftToken['type'] !== 'FIELD') {
                $this->invalid("Operator '{$op}' requires a field on the left-hand side in SQL mode");
            }

            if ($rightToken['type'] === 'FIELD') {
                $this->invalid("Operator '{$op}' does not support a field on the right-hand side in SQL mode");
            }
        }
    }

    private function dryRunListValue(): void
    {
        $this->expect('LPAREN');
        $count = 0;
        while (true) {
            if ($this->peek('RPAREN')) {
                $this->consume();
                break;
            }
            $token = $this->consume();
            if (! in_array($token['type'], ['VALUE', 'DATE_FUNC'], true)) {
                $this->invalid("Expected scalar list value, got '{$token['value']}'");
            }
            $count++;
            if ($this->peek('COMMA')) {
                $this->consume();

                continue;
            }
            $this->expect('RPAREN');
            break;
        }
        if ($count === 0) {
            $this->invalid('List operator requires at least one value');
        }
    }

    // ── Recursive descent — SQL compilation (run) ─────────────────────────

    private function applyOr(Builder $q, bool $isOr): void
    {
        $this->applyAnd($q, $isOr);
        while ($this->peek('OR')) {
            $this->consume();
            $this->applyAnd($q, true);
        }
    }

    private function applyAnd(Builder $q, bool $isOr): void
    {
        $this->applyPrimary($q, $isOr);
        while ($this->peek('AND')) {
            $this->consume();
            $this->applyPrimary($q, false);
        }
    }

    private function applyPrimary(Builder $q, bool $isOr): void
    {
        if ($this->peek('LPAREN')) {
            $this->consume();
            $method = $isOr ? 'orWhere' : 'where';
            $q->$method(function (Builder $sub) {
                $this->applyOr($sub, false);
            });
            $this->expect('RPAREN');

            return;
        }

        $this->applyCondition($q, $isOr);
    }

    private function applyCondition(Builder $q, bool $isOr): void
    {
        $leftToken = $this->consume();
        if (! in_array($leftToken['type'], ['FIELD', 'SYSVAR', 'VALUE', 'DATE_FUNC'], true)) {
            $this->invalid("Expected scalar token, got '{$leftToken['value']}'");
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            $this->invalid("Expected operator, got '{$opToken['value']}'");
        }

        $op = $opToken['value'];
        $leftIsField = $leftToken['type'] === 'FIELD';
        $leftIsSysVar = $leftToken['type'] === 'SYSVAR';

        // in / not in — list operand; field must be on the left
        if ($op === 'in' || $op === 'not in') {
            if (! $leftIsField) {
                $this->invalid("Operator '{$op}' requires a field on the left-hand side in SQL mode");
            }
            $list = $this->resolveListOperand();
            $field = $this->resolveFieldForQuery($leftToken['value'], $q);
            $not = $op === 'not in';

            if ($isOr) {
                $not ? $q->orWhereNotIn($field, $list) : $q->orWhereIn($field, $list);
            } else {
                $not ? $q->whereNotIn($field, $list) : $q->whereIn($field, $list);
            }

            return;
        }

        // JSON operators (?= and ?&)
        if ($op === '?=' || $op === '?&') {
            if (! in_array($leftToken['type'], ['FIELD', 'SYSVAR'], true)) {
                $this->invalid('JSON operators require a field or @-variable on the left-hand side in SQL mode');
            }

            $rightValue = $this->resolveScalarValue();
            $jsonField = $leftIsSysVar
                ? $this->resolveSysVar($leftToken['value'])
                : $leftToken['value'];

            $adapter = $this->findJsonAdapter((string) $jsonField);

            if (! $adapter) {
                $this->invalid("JSON operators require a JSON-path field (using '->' notation): {$jsonField}");
            }

            if ($op === '?=') {
                $adapter->applyJsonContains($q, (string) $jsonField, $rightValue, $isOr);
            } else {
                $adapter->applyJsonHasKey($q, (string) $jsonField, $rightValue, $isOr);
            }

            return;
        }

        // Scalar comparison
        $rightToken = $this->consume();
        if (! in_array($rightToken['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
            $this->invalid("Expected scalar token on right-hand side, got '{$rightToken['value']}'");
        }

        $rightIsField = $rightToken['type'] === 'FIELD';

        if (! $this->isReversibleSqlOperator($op)) {
            if (! $leftIsField) {
                $this->invalid("Operator '{$op}' requires a field on the left-hand side in SQL mode");
            }

            if ($rightIsField) {
                $this->invalid("Operator '{$op}' does not support a field on the right-hand side in SQL mode");
            }

            $field = $this->resolveFieldForQuery($leftToken['value'], $q);
            $value = $this->resolveScalarToken($rightToken);
            $this->applySqlScalarCondition($q, $field, $op, $value, $isOr);

            return;
        }

        if ($leftIsField && $rightIsField) {
            $leftField = $this->resolveFieldForQuery($leftToken['value'], $q);
            $rightField = $this->resolveFieldForQuery($rightToken['value'], $q);

            if ($isOr) {
                $q->orWhereColumn($leftField, $op, $rightField);
            } else {
                $q->whereColumn($leftField, $op, $rightField);
            }

            return;
        }

        if ($leftIsField) {
            $field = $this->resolveFieldForQuery($leftToken['value'], $q);
            $value = $this->resolveScalarToken($rightToken);
            $this->applySqlScalarCondition($q, $field, $op, $value, $isOr);

            return;
        }

        if ($rightIsField) {
            $field = $this->resolveFieldForQuery($rightToken['value'], $q);
            $value = $this->resolveScalarToken($leftToken);
            $this->applySqlScalarCondition($q, $field, $this->invertOrderedOperator($op), $value, $isOr);

            return;
        }

        $leftValue = $this->resolveScalarToken($leftToken);
        $rightValue = $this->resolveScalarToken($rightToken);

        $this->applySqlLiteralCondition($q, $op, $leftValue, $rightValue, $isOr);
    }

    private function applySqlScalarCondition(
        Builder $q,
        string $field,
        string $op,
        mixed $value,
        bool $isOr,
    ): void {
        switch ($op) {
            case '=':
                if ($value === null) {
                    $isOr ? $q->orWhereNull($field) : $q->whereNull($field);

                    return;
                }
                $isOr ? $q->orWhere($field, '=', $value) : $q->where($field, '=', $value);

                return;

            case '!=':
                if ($value === null) {
                    $isOr ? $q->orWhereNotNull($field) : $q->whereNotNull($field);

                    return;
                }
                $isOr ? $q->orWhere($field, '!=', $value) : $q->where($field, '!=', $value);

                return;

            default:
                // like, not like, >, <, >=, <=
                $isOr ? $q->orWhere($field, $op, $value) : $q->where($field, $op, $value);
        }
    }

    private function applySqlLiteralCondition(
        Builder $q,
        string $op,
        mixed $leftValue,
        mixed $rightValue,
        bool $isOr,
    ): void {
        $method = $isOr ? 'orWhereRaw' : 'whereRaw';
        $q->$method('? '.$op.' ?', [$leftValue, $rightValue]);
    }

    private function isReversibleSqlOperator(string $op): bool
    {
        return in_array($op, ['=', '!=', '>', '<', '>=', '<='], true);
    }

    private function invertOrderedOperator(string $op): string
    {
        return match ($op) {
            '>' => '<',
            '<' => '>',
            '>=' => '<=',
            '<=' => '>=',
            default => $op,
        };
    }

    // ── Field / value resolution helpers ─────────────────────────────────────

    private function resolveFieldForQuery(string $field, Builder $q): string
    {
        foreach ($this->queryFieldAdapters as $adapter) {
            if ($adapter->supports($field)) {
                $resolved = $adapter->resolveForQuery($field, $q);

                return is_string($resolved) ? $resolved : (string) $resolved;
            }
        }

        return $field;
    }

    private function resolveSysVar(string $name): mixed
    {
        if (! $this->resolveReferences) {
            return $name;
        }

        return data_get($this->sqlContext, substr($name, 1));
    }

    private function resolveScalarValue(): mixed
    {
        $token = $this->consume();

        return $this->resolveScalarToken($token);
    }

    private function resolveScalarToken(array $token): mixed
    {
        return match ($token['type']) {
            'DATE_FUNC' => $this->resolveDateFunction($token['value']),
            'SYSVAR' => $this->resolveSysVar($token['value']),
            'FIELD' => $token['value'], // caller decides what to do with it
            default => $this->castLiteral($token['value']),
        };
    }

    private function resolveListOperand(): array
    {
        $this->expect('LPAREN');
        $values = [];

        while (true) {
            if ($this->peek('RPAREN')) {
                $this->consume();
                break;
            }

            $token = $this->consume();
            if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'SYSVAR'], true)) {
                $this->invalid("Expected list value, got '{$token['type']}' ('{$token['value']}')");
            }

            $values[] = $this->resolveScalarToken($token);

            if ($this->peek('COMMA')) {
                $this->consume();

                continue;
            }

            $this->expect('RPAREN');
            break;
        }

        if ($values === []) {
            $this->invalid('List operator requires at least one value');
        }

        return $values;
    }

    private function findJsonAdapter(string $field): ?JsonFieldAdapter
    {
        foreach ($this->queryFieldAdapters as $adapter) {
            if ($adapter instanceof JsonFieldAdapter && $adapter->supports($field)) {
                return $adapter;
            }
        }

        return null;
    }
}
