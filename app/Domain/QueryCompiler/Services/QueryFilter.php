<?php

namespace App\Domain\QueryCompiler\Services;

use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\Records\Services\RelationJoinResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QueryFilter
{
    private Builder $query;

    private ?RelationJoinResolver $relationJoinResolver = null;

    private array $tokens = [];

    private int $pos = 0;

    private array $allowedFields = [];

    private array $evaluationContext = [];

    private bool $allowUnknownFields = false;

    private bool $isEvaluating = false;

    private bool $resolveSystemReferences = false;

    private const VALUE_KEYWORDS = ['true', 'false', 'null'];

    private const OPERATORS = [
        'is not null',
        'not like',
        'not in',
        '!=',
        '>=',
        '<=',
        'is null',
        'like',
        'in',
        '>',
        '<',
        '=',
    ];

    // Simple no-arg date functions
    private const DATE_FUNCTIONS_SIMPLE = [
        'now',
        'today',
        'yesterday',
        'tomorrow',
        'thisweek',
        'lastweek',
        'nextweek',
        'thismonth',
        'lastmonth',
        'nextmonth',
        'thisyear',
        'lastyear',
        'nextyear',
        'startofday',
        'endofday',
        'startofweek',
        'endofweek',
        'startofmonth',
        'endofmonth',
        'startofyear',
        'endofyear',
    ];

    // Parameterized date functions (take a single integer argument)
    private const DATE_FUNCTIONS_PARAM = [
        'daysago',
        'daysfromnow',
        'weeksago',
        'weeksfromnow',
        'monthsago',
        'monthsfromnow',
        'yearsago',
        'yearsfromnow',
    ];

    // -------------------------------------------------------------------------

    public function __construct(Builder $query, array $allowedFields)
    {
        $this->query = $query;
        $this->allowedFields = $allowedFields;
    }

    public static function for(Builder $query, array $allowedFields): self
    {
        return new self($query, $allowedFields);
    }

    public function withRelationJoinResolver(RelationJoinResolver $resolver): self
    {
        $this->relationJoinResolver = $resolver;

        return $this;
    }

    public function lint(?string $filter = null, bool $inMemory = false): void
    {
        $filter = trim($filter ?? '');
        if ($filter === '') {
            return;
        }

        $this->tokens = $this->tokenize($filter);
        $this->pos = 0;

        if ($inMemory) {
            $this->isEvaluating = true;
            $this->allowUnknownFields = true;
            try {
                $this->evaluateExpr();
            } finally {
                $this->isEvaluating = false;
                $this->allowUnknownFields = false;
            }
        } else {
            $this->applyExpr($this->query, false);
        }
    }

    public function run(string $filter, array $context = []): Builder
    {
        $filter = trim($filter);
        if ($filter === '') {
            return $this->query;
        }

        $this->evaluationContext = $context !== []
            ? $context
            : $this->buildRuntimeContext();
        $this->resolveSystemReferences = true;

        try {
            $this->tokens = $this->tokenize($filter);
            $this->pos = 0;

            $this->applyExpr($this->query, false);
        } finally {
            $this->resolveSystemReferences = false;
            $this->evaluationContext = [];
        }

        return $this->query;
    }

    public function evaluate(string $filter, array $context = []): bool
    {
        $filter = trim($filter);
        if ($filter === '') {
            return true;
        }

        $this->evaluationContext = $context;
        $this->allowUnknownFields = true;
        $this->isEvaluating = true;

        try {
            $this->tokens = $this->tokenize($filter);
            $this->pos = 0;

            $result = $this->evaluateExpr();

            if (isset($this->tokens[$this->pos])) {
                $token = $this->tokens[$this->pos];
                $this->invalid("Unexpected token '{$token['value']}'");
            }

            return $result;
        } finally {
            $this->allowUnknownFields = false;
            $this->isEvaluating = false;
            $this->evaluationContext = [];
        }
    }

    // ── Recursive descent ─────────────────────────────────────────────────────

    private function applyExpr(Builder $q, bool $isOr): void
    {
        $this->applyOr($q, $isOr);
    }

    private function evaluateExpr(): bool
    {
        return $this->evaluateOr();
    }

    private function applyOr(Builder $q, bool $isOr): void
    {
        $this->applyAnd($q, $isOr);

        while ($this->peek('OR')) {
            $this->consume();
            $this->applyAnd($q, true);
        }
    }

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

    private function applyAnd(Builder $q, bool $isOr): void
    {
        $this->applyPrimary($q, $isOr);

        while ($this->peek('AND')) {
            $this->consume();
            $this->applyPrimary($q, false);
        }
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

    private function applyPrimary(Builder $q, bool $isOr): void
    {
        if ($this->peek('LPAREN')) {
            $this->consume();

            $method = $isOr ? 'orWhere' : 'where';
            $q->$method(function (Builder $sub) {
                $this->applyExpr($sub, false);
            });

            $this->expect('RPAREN');

            return;
        }

        $this->applyCondition($q, $isOr);
    }

    private function evaluatePrimary(): bool
    {
        if ($this->peek('LPAREN')) {
            $this->consume();
            $result = $this->evaluateExpr();
            $this->expect('RPAREN');

            return $result;
        }

        return $this->evaluateCondition();
    }

    private function applyCondition(Builder $q, bool $isOr): void
    {
        $fieldToken = $this->consume();
        if ($fieldToken['type'] !== 'FIELD') {
            $this->invalid("Expected field name, got '{$fieldToken['value']}'");
        }

        if ($this->isSystemReference($fieldToken['value'])) {
            $this->invalid('Invalid rule expression: expected FIELD OP VALUE and field cannot be @-prefixed.');
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            $this->invalid("Expected operator, got '{$opToken['value']}'");
        }

        $field = $fieldToken['value'];
        $op = strtolower($opToken['value']);

        if (str_contains($field, '.') && $this->relationJoinResolver) {
            $field = $this->relationJoinResolver->resolveField($field);
        }

        switch ($op) {
            case 'is null':
                $isOr ? $q->orWhereNull($field) : $q->whereNull($field);

                return;

            case 'is not null':
                $isOr ? $q->orWhereNotNull($field) : $q->whereNotNull($field);

                return;

            case 'in':
                $value = $this->parseListValue();
                $isOr ? $q->orWhereIn($field, $value) : $q->whereIn($field, $value);

                return;

            case 'not in':
                $value = $this->parseListValue();
                $isOr ? $q->orWhereNotIn($field, $value) : $q->whereNotIn($field, $value);

                return;

            case '=':
                $value = $this->parseScalarValue();

                if ($value === null) {
                    $isOr ? $q->orWhereNull($field) : $q->whereNull($field);

                    return;
                }

                $isOr
                    ? $q->orWhere($field, '=', $value)
                    : $q->where($field, '=', $value);

                return;

            case '!=':
                $value = $this->parseScalarValue();

                if ($value === null) {
                    $isOr ? $q->orWhereNotNull($field) : $q->whereNotNull($field);

                    return;
                }

                $isOr
                    ? $q->orWhere($field, '!=', $value)
                    : $q->where($field, '!=', $value);

                return;

            default:
                $value = $this->parseScalarValue();

                $isOr
                    ? $q->orWhere($field, $op, $value)
                    : $q->where($field, $op, $value);
        }
    }

    private function evaluateCondition(): bool
    {
        $fieldToken = $this->consume();
        if ($fieldToken['type'] !== 'FIELD') {
            $this->invalid("Expected field name, got '{$fieldToken['value']}'");
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            $this->invalid("Expected operator, got '{$opToken['value']}'");
        }

        $fieldValue = $this->resolveContextValue($fieldToken['value']);
        $op = strtolower($opToken['value']);

        return match ($op) {
            'is null' => $fieldValue === null,
            'is not null' => $fieldValue !== null,
            'in' => in_array($fieldValue, $this->parseListValue(), false),
            'not in' => ! in_array($fieldValue, $this->parseListValue(), false),
            'like' => $this->matchesLike($fieldValue, $this->parseScalarValue()),
            'not like' => ! $this->matchesLike($fieldValue, $this->parseScalarValue()),
            '=', '!=', '>', '<', '>=', '<=' => $this->compareValues($fieldValue, $op, $this->parseScalarValue()),
            default => $this->invalid("Unsupported operator '{$op}' for evaluation"),
        };
    }

    private function resolveContextValue(string $field): mixed
    {
        $path = str_starts_with($field, '@')
            ? substr($field, 1)
            : $field;

        return data_get($this->evaluationContext, $path);
    }

    private function buildRuntimeContext(): array
    {
        $request = request();
        $authenticatedUser = Auth::user();

        $authContext = is_object($authenticatedUser) && method_exists($authenticatedUser, 'getAttributes')
            ? $authenticatedUser->getAttributes()
            : null;

        return [
            'request' => [
                'body' => $request->all(),
                'param' => $request->route()?->parameters() ?? [],
                'query' => $request->query(),
                'auth' => $authContext,
            ],
        ];
    }

    private function shouldResolveSystemReferences(): bool
    {
        return $this->isEvaluating || $this->resolveSystemReferences;
    }

    // ── Value helpers ─────────────────────────────────────────────────────────

    private function castValue(string $v): mixed
    {
        if (strtolower($v) === 'null') {
            return null;
        }
        if (strtolower($v) === 'true') {
            return true;
        }
        if (strtolower($v) === 'false') {
            return false;
        }
        if (is_numeric($v)) {
            return $v + 0;
        }

        return $v;
    }

    /**
     * Resolve a DATE_FUNC token (e.g. "today()", "daysago(5)") to a Carbon instance.
     * Token value is always lowercase and already validated during tokenization.
     */
    private function resolveDateFunction(string $v): Carbon
    {
        if (! preg_match('/^(\w+)\((\d*)\)$/', $v, $m)) {
            $this->invalid("Malformed date function: {$v}");
        }

        $name = $m[1];
        $arg = $m[2] !== '' ? (int) $m[2] : null;

        if (in_array($name, self::DATE_FUNCTIONS_PARAM, true)) {
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
        };
    }

    private function parseListValue(): array
    {
        $this->expect('LPAREN');

        $values = [];

        while (true) {
            if ($this->peek('RPAREN')) {
                $this->consume();

                break;
            }

            $token = $this->consume();

            if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'FIELD'], true)) {
                $this->invalid("Expected list value, got '{$token['type']}' ('{$token['value']}')");
            }

            if ($token['type'] === 'DATE_FUNC') {
                $values[] = $this->resolveDateFunction($token['value']);
            } elseif ($token['type'] === 'FIELD' && $this->isSystemReference($token['value'])) {
                $values[] = $this->shouldResolveSystemReferences()
                    ? $this->resolveContextValue($token['value'])
                    : $token['value'];
            } else {
                $values[] = $this->castValue($token['value']);
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

    private function parseScalarValue(): mixed
    {
        $token = $this->consume();

        if (! in_array($token['type'], ['VALUE', 'DATE_FUNC', 'FIELD'], true)) {
            $this->invalid("Expected value token, got '{$token['type']}' ('{$token['value']}')");
        }

        if ($token['type'] === 'FIELD') {
            if (! $this->isSystemReference($token['value'])) {
                $this->invalid("Expected value token, got 'FIELD' ('{$token['value']}')");
            }

            return $this->shouldResolveSystemReferences()
                ? $this->resolveContextValue($token['value'])
                : $token['value'];
        }

        return $token['type'] === 'DATE_FUNC'
            ? $this->resolveDateFunction($token['value'])
            : $this->castValue($token['value']);
    }

    private function isSystemReference(string $value): bool
    {
        return str_starts_with($value, '@');
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

    private function compareValues(mixed $left, string $op, mixed $right): bool
    {
        if ($left instanceof Carbon) {
            $left = $left->getTimestamp();
        }

        if ($right instanceof Carbon) {
            $right = $right->getTimestamp();
        }

        return match ($op) {
            '=' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            default => $this->invalid("Unsupported comparison operator '{$op}'"),
        };
    }

    // ── Tokenizer ─────────────────────────────────────────────────────────────

    private function tokenize(string $src): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($src);
        $allDateFuncs = array_merge(self::DATE_FUNCTIONS_SIMPLE, self::DATE_FUNCTIONS_PARAM);

        while ($i < $len) {
            if (ctype_space($src[$i])) {
                $i++;

                continue;
            }

            if ($src[$i] === '(') {
                $tokens[] = ['type' => 'LPAREN', 'value' => '('];
                $i++;

                continue;
            }
            if ($src[$i] === ')') {
                $tokens[] = ['type' => 'RPAREN', 'value' => ')'];
                $i++;

                continue;
            }
            if ($src[$i] === ',') {
                $tokens[] = ['type' => 'COMMA', 'value' => ','];
                $i++;

                continue;
            }

            if (substr($src, $i, 2) === '&&') {
                $tokens[] = ['type' => 'AND', 'value' => '&&'];
                $i += 2;

                continue;
            }
            if (substr($src, $i, 2) === '||') {
                $tokens[] = ['type' => 'OR', 'value' => '||'];
                $i += 2;

                continue;
            }

            // quoted string value
            if ($src[$i] === '"' || $src[$i] === "'") {
                $q = $src[$i];
                $j = $i + 1;
                $val = '';
                while ($j < $len && $src[$j] !== $q) {
                    $val .= $src[$j++];
                }
                $tokens[] = ['type' => 'VALUE', 'value' => $val];
                $i = $j + 1;

                continue;
            }

            // comparison operators (longest match first)
            $matched = false;
            foreach (self::OPERATORS as $op) {
                if (strtolower(substr($src, $i, strlen($op))) === $op) {
                    $tokens[] = ['type' => 'OP', 'value' => $op];
                    $i += strlen($op);
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }

            // Numeric literal (bare, e.g. 42 or 3.14)
            if (ctype_digit($src[$i]) || ($src[$i] === '-' && isset($src[$i + 1]) && ctype_digit($src[$i + 1]))) {
                $num = '';
                if ($src[$i] === '-') {
                    $num .= $src[$i++];
                }
                while ($i < $len && (ctype_digit($src[$i]) || $src[$i] === '.')) {
                    $num .= $src[$i++];
                }
                $tokens[] = ['type' => 'VALUE', 'value' => $num];

                continue;
            }

            // Read a bare word (stops at whitespace, parens, or logical symbols)
            $word = '';
            while ($i < $len && ! ctype_space($src[$i]) && ! in_array($src[$i], ['(', ')', ',', '&', '|'], true)) {
                $word .= $src[$i++];
            }

            if ($word === '') {
                continue;
            }

            $lower = strtolower($word);

            // Keyword logical operators (word form)
            if ($lower === 'and') {
                $tokens[] = ['type' => 'AND', 'value' => '&&'];

                continue;
            }
            if ($lower === 'or') {
                $tokens[] = ['type' => 'OR', 'value' => '||'];

                continue;
            }

            // Function call — word followed immediately by '('
            if (isset($src[$i]) && $src[$i] === '(') {
                if (! in_array($lower, $allDateFuncs, true)) {
                    $this->invalid("Unknown function: {$lower}()");
                }

                // Capture the full argument list including surrounding parens
                $j = $i;
                $depth = 0;
                $args = '';
                while ($j < $len) {
                    if ($src[$j] === '(') {
                        $depth++;
                    }
                    if ($src[$j] === ')') {
                        $depth--;
                        $args .= $src[$j++];
                        if ($depth === 0) {
                            break;
                        }

                        continue;
                    }
                    $args .= $src[$j++];
                }
                $i = $j;

                $tokens[] = ['type' => 'DATE_FUNC', 'value' => $lower.$args];

                continue;
            }

            // Value keywords: true, false, null — always valid as bare words on the RHS
            if (in_array($lower, self::VALUE_KEYWORDS, true)) {
                $tokens[] = ['type' => 'VALUE', 'value' => $lower];

                continue;
            }

            // Everything else must be a known field name
            $isSystemField = $this->isSystemReference($word);

            if (! in_array($word, $this->allowedFields, true) && ! $this->allowUnknownFields && ! $isSystemField) {
                $this->invalid("Unknown field or variable: {$word}");
            }

            $tokens[] = ['type' => 'FIELD', 'value' => $word];
        }

        return $tokens;
    }

    // ── Token helpers ─────────────────────────────────────────────────────────

    private function peek(string $type): bool
    {
        return isset($this->tokens[$this->pos])
            && $this->tokens[$this->pos]['type'] === $type;
    }

    private function consume(): array
    {
        $token = $this->tokens[$this->pos] ?? null;
        if (! $token) {
            $this->invalid('Unexpected end of filter string');
        }
        $this->pos++;

        return $token;
    }

    private function expect(string $type): array
    {
        $token = $this->consume();
        if ($token['type'] !== $type) {
            $this->invalid("Expected {$type}, got {$token['type']} ('{$token['value']}')");
        }

        return $token;
    }

    private function invalid(string $message): never
    {
        throw new InvalidRuleExpressionException($message);
    }
}
