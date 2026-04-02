<?php

namespace App\Domain\RuleEngine;

use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\RuleEngine\Contracts\FieldResolverAdapter;
use Carbon\Carbon;

/**
 * General-purpose rule/filter evaluator.
 *
 * Evaluates filter expressions against an in-memory context (array).
 * Uses recursive-descent parsing directly on the token stream — no AST.
 *
 * Expression examples:
 *   user = @request.auth.id
 *
 *   @request.body.user = @request.auth.id
 *   status = "active" && role != "guest"
 *   author.verified = true
 *   metadata->tags ?= "php"
 *
 * Supported operators: =, !=, >, <, >=, <=, like, not like, in, not in, ?=, ?&
 * Null checks:  field = null  /  field != null
 * Logical:      &&  ||
 *
 * @-variable prefixes validated during lint():
 *
 *   @request.auth.*  @request.body.*  @request.param.*  @request.query.*
 */
class RuleEngine
{
    // Known valid @-prefixes for system variables.
    private const SYSVAR_PREFIXES = [
        '@request.auth.',
        '@request.body.',
        '@request.param.',
        '@request.query.',
    ];

    private array $tokens = [];

    private int $pos = 0;

    private array $evaluationContext = [];

    /** @var list<string> Allowed field names for lint() validation */
    protected array $allowedFields = [];

    /** @var list<FieldResolverAdapter> */
    private array $fieldResolverAdapters = [];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a standalone RuleEngine instance with a set of allowed fields.
     */
    public static function make(array $allowedFields = []): static
    {
        $instance = new static;
        $instance->allowedFields = $allowedFields;

        return $instance;
    }

    /**
     * Set the allowed fields list (fluent).
     */
    public function withAllowedFields(array $allowedFields): static
    {
        $this->allowedFields = $allowedFields;

        return $this;
    }

    /**
     * Evaluate a filter expression against a context and return a boolean result.
     */
    public function evaluate(string $filter, array $context = []): bool
    {
        $filter = trim($filter);
        if ($filter === '') {
            return true;
        }

        $this->evaluationContext = $context;

        try {
            $this->tokens = $this->makeTokenizer(allowUnknownFields: true)->tokenize($filter);
            $this->pos = 0;

            $result = $this->evaluateOr();

            if (isset($this->tokens[$this->pos])) {
                $token = $this->tokens[$this->pos];
                $this->invalid("Unexpected token '{$token['value']}'");
            }

            return $result;
        } finally {
            $this->evaluationContext = [];
        }
    }

    /**
     * Lint (validate) a filter expression without running it.
     * Non-@-prefixed identifiers are validated against the stored $allowedFields.
     *
     * @-prefixed identifiers are validated against known system variable prefixes.
     *
     * @throws InvalidRuleExpressionException
     */
    public function lint(string $filter, bool $inMemory = true): void
    {
        $filter = trim($filter);
        if ($filter === '') {
            return;
        }

        // Tokenize with field validation ON using the stored allowed fields.
        $this->tokens = $this->makeTokenizer(
            allowedFields: $this->allowedFields,
            allowUnknownFields: false,
        )->tokenize($filter);

        // Now validate @-variable prefixes across all SYSVAR tokens.
        foreach ($this->tokens as $token) {
            if ($token['type'] === 'SYSVAR') {
                $this->validateSysVar($token['value']);
            }
        }

        // Walk the token stream to check grammar (dry-run evaluate).
        $this->pos = 0;
        $this->dryRunOr();
    }

    /**
     * Add a field resolver adapter for custom field path resolution in evaluate().
     */
    public function addFieldResolverAdapter(FieldResolverAdapter $adapter): static
    {
        $this->fieldResolverAdapters[] = $adapter;

        return $this;
    }

    // ── @-variable validation ──────────────────────────────────────────────

    protected function validateSysVar(string $name): void
    {
        foreach (self::SYSVAR_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return;
            }
        }

        $valid = implode(', ', self::SYSVAR_PREFIXES);
        $this->invalid("Invalid system variable '{$name}'. Must start with one of: {$valid}");
    }

    // ── Recursive descent (evaluate mode) ────────────────────────────────────

    private function evaluateOr(): bool
    {
        $result = $this->evaluateAnd();

        while ($this->peek('OR')) {
            $this->consume();
            $right = $this->evaluateAnd();
            $result = $result || $right;
        }

        return $result;
    }

    private function evaluateAnd(): bool
    {
        $result = $this->evaluatePrimary();

        while ($this->peek('AND')) {
            $this->consume();
            $right = $this->evaluatePrimary();
            $result = $result && $right;
        }

        return $result;
    }

    private function evaluatePrimary(): bool
    {
        if ($this->peek('LPAREN')) {
            $this->consume();
            $result = $this->evaluateOr();
            $this->expect('RPAREN');

            return $result;
        }

        return $this->evaluateCondition();
    }

    private function evaluateCondition(): bool
    {
        $leftToken = $this->consume();
        if (! in_array($leftToken['type'], ['FIELD', 'SYSVAR'], true)) {
            $this->invalid("Expected field or @-variable on left-hand side, got '{$leftToken['value']}'");
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            $this->invalid("Expected operator, got '{$opToken['value']}'");
        }

        $op = $opToken['value'];
        $leftValue = $this->resolveOperand($leftToken);

        // in / not in — list operand
        if ($op === 'in' || $op === 'not in') {
            $list = $this->parseListOperand();
            $result = in_array($leftValue, $list, false);

            return $op === 'in' ? $result : ! $result;
        }

        $rightValue = $this->parseScalarOperand();

        return $this->applyOperator($leftValue, $op, $rightValue);
    }

    // ── Recursive descent (dry-run / lint mode) ────────────────────────────

    private function dryRunOr(): void
    {
        $this->dryRunAnd();

        while ($this->peek('OR')) {
            $this->consume();
            $this->dryRunAnd();
        }
    }

    private function dryRunAnd(): void
    {
        $this->dryRunPrimary();

        while ($this->peek('AND')) {
            $this->consume();
            $this->dryRunPrimary();
        }
    }

    private function dryRunPrimary(): void
    {
        if ($this->peek('LPAREN')) {
            $this->consume();
            $this->dryRunOr();
            $this->expect('RPAREN');

            return;
        }

        $this->dryRunCondition();
    }

    private function dryRunCondition(): void
    {
        $leftToken = $this->consume();
        if (! in_array($leftToken['type'], ['FIELD', 'SYSVAR'], true)) {
            $this->invalid("Expected field or @-variable on left-hand side, got '{$leftToken['value']}'");
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            $this->invalid("Expected operator, got '{$opToken['value']}'");
        }

        $op = $opToken['value'];

        if ($op === 'in' || $op === 'not in') {
            $this->dryRunListOperand();

            return;
        }

        $this->dryRunScalarOperand();
    }

    private function dryRunScalarOperand(): void
    {
        $token = $this->consume();
        if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
            $this->invalid("Expected value on right-hand side, got '{$token['value']}'");
        }
    }

    private function dryRunListOperand(): void
    {
        $this->expect('LPAREN');

        $count = 0;
        while (true) {
            if ($this->peek('RPAREN')) {
                $this->consume();
                break;
            }

            $token = $this->consume();
            if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
                $this->invalid("Expected list value, got '{$token['value']}'");
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

    // ── Operand resolution ─────────────────────────────────────────────────

    /**
     * Resolve a FIELD or SYSVAR token to its value from the evaluation context.
     */
    protected function resolveOperand(array $token): mixed
    {
        $name = $token['value'];

        // Check adapters first
        foreach ($this->fieldResolverAdapters as $adapter) {
            if ($adapter->supports($name)) {
                return $adapter->resolveForEvaluation($name, $this->evaluationContext);
            }
        }

        $path = str_starts_with($name, '@') ? substr($name, 1) : $name;

        return data_get($this->evaluationContext, $path);
    }

    /**
     * Parse and resolve the right-hand side scalar operand.
     */
    protected function parseScalarOperand(): mixed
    {
        $token = $this->consume();

        if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
            $this->invalid("Expected value token on right-hand side, got '{$token['type']}' ('{$token['value']}')");
        }

        if ($token['type'] === 'DATE_FUNC') {
            return $this->resolveDateFunction($token['value']);
        }

        if (in_array($token['type'], ['FIELD', 'SYSVAR'], true)) {
            return $this->resolveOperand($token);
        }

        return $this->castLiteral($token['value']);
    }

    /**
     * Parse and resolve a list operand: (val1, val2, ...)
     */
    protected function parseListOperand(): array
    {
        $this->expect('LPAREN');

        $values = [];

        while (true) {
            if ($this->peek('RPAREN')) {
                $this->consume();
                break;
            }

            $token = $this->consume();
            if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'FIELD', 'SYSVAR'], true)) {
                $this->invalid("Expected list value, got '{$token['type']}' ('{$token['value']}')");
            }

            if ($token['type'] === 'DATE_FUNC') {
                $values[] = $this->resolveDateFunction($token['value']);
            } elseif (in_array($token['type'], ['FIELD', 'SYSVAR'], true)) {
                $values[] = $this->resolveOperand($token);
            } else {
                $values[] = $this->castLiteral($token['value']);
            }

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

    // ── Operator application ───────────────────────────────────────────────

    protected function applyOperator(mixed $left, string $op, mixed $right): bool
    {
        return match ($op) {
            '=' => $this->compareEqual($left, $right),
            '!=' => ! $this->compareEqual($left, $right),
            'like' => $this->matchesLike($left, $right),
            'not like' => ! $this->matchesLike($left, $right),
            '?=' => $this->jsonContains($left, $right),
            '?&' => $this->jsonHasKey($left, $right),
            '>', '<', '>=', '<=' => $this->compareOrdered($left, $op, $right),
            default => $this->invalid("Unsupported operator '{$op}'"),
        };
    }

    private function compareEqual(mixed $left, mixed $right): bool
    {
        if ($left instanceof Carbon) {
            $left = $left->getTimestamp();
        }
        if ($right instanceof Carbon) {
            $right = $right->getTimestamp();
        }

        // Null equality uses loose comparison (consistent with SQL NULL behaviour)
        if ($right === null || $left === null) {
            return $left === $right;
        }

        return $left == $right;
    }

    private function compareOrdered(mixed $left, string $op, mixed $right): bool
    {
        if ($left instanceof Carbon) {
            $left = $left->getTimestamp();
        }
        if ($right instanceof Carbon) {
            $right = $right->getTimestamp();
        }

        return match ($op) {
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
        };
    }

    private function matchesLike(mixed $subject, mixed $pattern): bool
    {
        if ($subject === null || $pattern === null) {
            return false;
        }

        $subject = (string) $subject;
        $pattern = (string) $pattern;
        $regex = '/^'.str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')).'$/u';

        return preg_match($regex, $subject) === 1;
    }

    /**
     * JSON contains — checks if $haystack (array/collection) contains $needle.
     * Expression: metadata->tags ?= "php"
     */
    private function jsonContains(mixed $haystack, mixed $needle): bool
    {
        if ($haystack === null) {
            return false;
        }

        if (is_string($haystack)) {
            $decoded = json_decode($haystack, true);
            if (is_array($decoded)) {
                $haystack = $decoded;
            }
        }

        if (is_array($haystack)) {
            return in_array($needle, $haystack, false);
        }

        return false;
    }

    /**
     * JSON has key — checks if $subject (object/map) has key $key.
     * Expression: metadata->settings ?& "theme"
     */
    private function jsonHasKey(mixed $subject, mixed $key): bool
    {
        if ($subject === null || $key === null) {
            return false;
        }

        if (is_string($subject)) {
            $decoded = json_decode($subject, true);
            if (is_array($decoded)) {
                $subject = $decoded;
            }
        }

        if (is_array($subject)) {
            return array_key_exists((string) $key, $subject);
        }

        return false;
    }

    // ── Value helpers ─────────────────────────────────────────────────────────

    protected function castLiteral(string $v): mixed
    {
        $lower = strtolower($v);

        if ($lower === 'null') {
            return null;
        }
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if (is_numeric($v)) {
            return $v + 0;
        }

        return $v;
    }

    protected function resolveDateFunction(string $v): Carbon
    {
        if (! preg_match('/^(\w+)\((\d*)\)$/', $v, $m)) {
            $this->invalid("Malformed date function: {$v}");
        }

        $name = $m[1];
        $arg = $m[2] !== '' ? (int) $m[2] : null;

        if (in_array($name, Tokenizer::getParamDateFunctions(), true)) {
            if ($arg === null) {
                $this->invalid("Date function {$name}() requires a numeric argument.");
            }

            return match ($name) {
                'daysago' => now()->subDays($arg),
                'daysfromnow' => now()->addDays($arg),
                'weeksago' => now()->subWeeks($arg),
                'weeksfromnow' => now()->addWeeks($arg),
                'monthsago' => now()->subMonths($arg),
                'monthsfromnow' => now()->addMonths($arg),
                'yearsago' => now()->subYears($arg),
                'yearsfromnow' => now()->addYears($arg),
            };
        }

        if ($arg !== null) {
            $this->invalid("Date function {$name}() does not accept arguments.");
        }

        return match ($name) {
            'now' => now(),
            'today' => now()->startOfDay(),
            'yesterday' => now()->subDay()->startOfDay(),
            'tomorrow' => now()->addDay()->startOfDay(),
            'thisweek' => now()->startOfWeek(),
            'lastweek' => now()->subWeek()->startOfWeek(),
            'nextweek' => now()->addWeek()->startOfWeek(),
            'thismonth' => now()->startOfMonth(),
            'lastmonth' => now()->subMonth()->startOfMonth(),
            'nextmonth' => now()->addMonth()->startOfMonth(),
            'thisyear' => now()->startOfYear(),
            'lastyear' => now()->subYear()->startOfYear(),
            'nextyear' => now()->addYear()->startOfYear(),
            'startofday' => now()->startOfDay(),
            'endofday' => now()->endOfDay(),
            'startofweek' => now()->startOfWeek(),
            'endofweek' => now()->endOfWeek(),
            'startofmonth' => now()->startOfMonth(),
            'endofmonth' => now()->endOfMonth(),
            'startofyear' => now()->startOfYear(),
            'endofyear' => now()->endOfYear(),
            default => $this->invalid("Unknown date function: {$name}"),
        };
    }

    // ── Token helpers ─────────────────────────────────────────────────────────

    protected function peek(string $type): bool
    {
        return isset($this->tokens[$this->pos])
            && $this->tokens[$this->pos]['type'] === $type;
    }

    protected function consume(): array
    {
        $token = $this->tokens[$this->pos] ?? null;
        if (! $token) {
            $this->invalid('Unexpected end of filter string');
        }
        $this->pos++;

        return $token;
    }

    protected function expect(string $type): array
    {
        $token = $this->consume();
        if ($token['type'] !== $type) {
            $this->invalid("Expected {$type}, got {$token['type']} ('{$token['value']}')");
        }

        return $token;
    }

    protected function invalid(string $message): never
    {
        throw new InvalidRuleExpressionException($message);
    }

    // ── Tokenizer factory ──────────────────────────────────────────────────

    protected function makeTokenizer(
        array $allowedFields = [],
        bool $allowUnknownFields = false,
    ): Tokenizer {
        return new Tokenizer($allowedFields, $allowUnknownFields);
    }

    // ── Setters for subclasses to populate token state ────────────────────

    protected function setTokens(array $tokens): void
    {
        $this->tokens = $tokens;
        $this->pos = 0;
    }

    protected function getEvaluationContext(): array
    {
        return $this->evaluationContext;
    }
}
