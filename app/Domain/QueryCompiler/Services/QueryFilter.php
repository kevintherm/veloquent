<?php

namespace App\Domain\QueryCompiler\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class QueryFilter
{
    private Builder $query;

    private array $tokens = [];

    private int $pos = 0;

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

    public function __construct(Builder $query)
    {
        $this->query = $query;
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
        $field = $this->consume()['value'];
        $op = strtolower($this->consume()['value']);

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
        $raw = $this->consume()['value'];

        return array_map(
            fn ($v) => $this->castValue(trim($v)),
            explode(',', trim($raw, '()'))
        );
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

            // in(...) list value — only when the previous token was an OP
            if ($src[$i] === '(' && ! empty($tokens) && ($tokens[array_key_last($tokens)]['type'] ?? '') === 'OP') {
                $j = $i;
                $depth = 0;
                $val = '';
                while ($j < $len) {
                    if ($src[$j] === '(') {
                        $depth++;
                    }
                    if ($src[$j] === ')') {
                        $depth--;
                        $val .= $src[$j++];
                        if ($depth === 0) {
                            break;
                        }

                        continue;
                    }
                    $val .= $src[$j++];
                }
                $tokens[] = ['type' => 'VALUE', 'value' => $val];
                $i = $j;

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

            // Read a bare word (stops at whitespace, parens, or logical chars)
            // NOTE: we stop at '(' so function names are read separately from their arg list
            $word = '';
            while ($i < $len && ! ctype_space($src[$i]) && ! in_array($src[$i], ['(', ')', '&', '|'])) {
                $word .= $src[$i++];
            }

            if ($word === '') {
                continue;
            }

            $lower = strtolower($word);

            if ($lower === 'and') {
                $tokens[] = ['type' => 'AND', 'value' => '&&'];

                continue;
            }
            if ($lower === 'or') {
                $tokens[] = ['type' => 'OR',  'value' => '||'];

                continue;
            }

            // If followed by '(' it must be a function call
            if (isset($src[$i]) && $src[$i] === '(') {
                // Capture the full argument list including parens
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

                // Validate: must be a known date function
                if (! in_array($lower, $allDateFuncs, true)) {
                    throw new \InvalidArgumentException("Unknown function: {$lower}()");
                }

                $tokens[] = ['type' => 'DATE_FUNC', 'value' => $lower.$args];

                continue;
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
