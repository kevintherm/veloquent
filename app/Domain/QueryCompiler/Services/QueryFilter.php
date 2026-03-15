<?php

namespace App\Domain\QueryCompiler\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class QueryFilter
{
    private Builder $query;

    private array $tokens = [];

    private int $pos = 0;

    private array $allowedFields = [];

    private const VALUE_KEYWORDS = ['true', 'false', 'null'];

    private const OPERATORS = [
        'is not null', 'not like', 'not in', '!=', '>=', '<=', 'is null', 'like', 'in', '>', '<', '=',
    ];

    // Simple no-arg date functions
    private const DATE_FUNCTIONS_SIMPLE = [
        'now', 'today', 'yesterday', 'tomorrow',
        'thisweek', 'lastweek', 'nextweek',
        'thismonth', 'lastmonth', 'nextmonth',
        'thisyear', 'lastyear', 'nextyear',
        'startofday', 'endofday',
        'startofweek', 'endofweek',
        'startofmonth', 'endofmonth',
        'startofyear', 'endofyear',
    ];

    // Parameterized date functions (take a single integer argument)
    private const DATE_FUNCTIONS_PARAM = [
        'daysago', 'daysfromnow',
        'weeksago', 'weeksfromnow',
        'monthsago', 'monthsfromnow',
        'yearsago', 'yearsfromnow',
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

    public function lint(?string $filter = null): void
    {
        $filter = trim($filter ?? '');
        if ($filter === '') {
            return;
        }

        $this->tokens = $this->tokenize($filter);
        $this->pos = 0;

        $this->applyExpr($this->query, false);
    }

    public function run(string $filter): Builder
    {
        $filter = trim($filter);
        if ($filter === '') {
            return $this->query;
        }

        $this->tokens = $this->tokenize($filter);
        $this->pos = 0;

        $this->applyExpr($this->query, false);

        return $this->query;
    }

    // ── Recursive descent ─────────────────────────────────────────────────────

    private function applyExpr(Builder $q, bool $isOr): void
    {
        $this->applyOr($q, $isOr);
    }

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
                $this->applyExpr($sub, false);
            });

            $this->expect('RPAREN');

            return;
        }

        $this->applyCondition($q, $isOr);
    }

    private function applyCondition(Builder $q, bool $isOr): void
    {
        $fieldToken = $this->consume();
        if ($fieldToken['type'] !== 'FIELD') {
            throw new \RuntimeException("Expected field name, got '{$fieldToken['value']}'");
        }

        $opToken = $this->consume();
        if ($opToken['type'] !== 'OP') {
            throw new \RuntimeException("Expected operator, got '{$opToken['value']}'");
        }

        $field = $fieldToken['value'];
        $op = strtolower($opToken['value']);

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

            default:
                $token = $this->consume();
                $value = $token['type'] === 'DATE_FUNC'
                    ? $this->resolveDateFunction($token['value'])
                    : $this->castValue($token['value']);

                $isOr
                    ? $q->orWhere($field, $op, $value)
                    : $q->where($field, $op, $value);
        }
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
            throw new \InvalidArgumentException("Malformed date function: {$v}");
        }

        $name = $m[1];
        $arg = $m[2] !== '' ? (int) $m[2] : null;

        if (in_array($name, self::DATE_FUNCTIONS_PARAM, true)) {
            if ($arg === null) {
                throw new \InvalidArgumentException("Date function {$name}() requires a numeric argument.");
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
            throw new \InvalidArgumentException("Date function {$name}() does not accept arguments.");
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

            if (! in_array($token['type'], ['VALUE', 'DATE_FUNC'], true)) {
                throw new \RuntimeException("Expected list value, got '{$token['type']}' ('{$token['value']}')");
            }

            $values[] = $token['type'] === 'DATE_FUNC'
                ? $this->resolveDateFunction($token['value'])
                : $this->castValue($token['value']);

            if ($this->peek('COMMA')) {
                $this->consume();

                continue;
            }

            $this->expect('RPAREN');

            break;
        }

        if ($values === []) {
            throw new \RuntimeException('List operator requires at least one value');
        }

        return $values;
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
                $tokens[] = ['type' => 'OR',  'value' => '||'];
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
                    throw new \InvalidArgumentException("Unknown function: {$lower}()");
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
            if (! in_array($word, $this->allowedFields, true)) {
                throw new \InvalidArgumentException("Unknown field or variable: {$word}");
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
            throw new \RuntimeException('Unexpected end of filter string');
        }
        $this->pos++;

        return $token;
    }

    private function expect(string $type): array
    {
        $token = $this->consume();
        if ($token['type'] !== $type) {
            throw new \RuntimeException("Expected {$type}, got {$token['type']} ('{$token['value']}')");
        }

        return $token;
    }
}
