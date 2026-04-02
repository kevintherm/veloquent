<?php

namespace App\Domain\RuleEngine;

use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;

/**
 * Converts a rule/filter expression string into a flat array of typed tokens.
 *
 * Token types:
 *  FIELD     — plain identifier (e.g. "user", "author.name", "meta->tags")
 *  SYSVAR    — @-prefixed system variable (e.g. "@request.auth.id")
 *  VALUE     — literal (string, number, true, false, null)
 *  DATE_FUNC — date helper call (e.g. "daysago(5)", "today()")
 *  OP        — comparison operator
 *  AND       — &&
 *  OR        — ||
 *  LPAREN    — (
 *  RPAREN    — )
 *  COMMA     — ,
 */
class Tokenizer
{
    // Normalized operator set — ordered longest-first to avoid partial matches.
    private const OPERATORS = [
        'not like',
        'not in',
        '!=',
        '>=',
        '<=',
        'like',
        'in',
        '>',
        '<',
        '=',
        // JSON operators
        '?=',
        '?&',
    ];

    private const VALUE_KEYWORDS = ['true', 'false', 'null'];

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

    private const DATE_FUNCTIONS_PARAM = [
        'daysago', 'daysfromnow',
        'weeksago', 'weeksfromnow',
        'monthsago', 'monthsfromnow',
        'yearsago', 'yearsfromnow',
    ];

    private array $allowedFields;

    private bool $allowUnknownFields;

    public function __construct(array $allowedFields = [], bool $allowUnknownFields = false)
    {
        $this->allowedFields = $allowedFields;
        $this->allowUnknownFields = $allowUnknownFields;
    }

    /**
     * Tokenize the given source string.
     *
     * @return array<int, array{type: string, value: string}>
     */
    public function tokenize(string $src): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($src);
        $allDateFuncs = array_merge(self::DATE_FUNCTIONS_SIMPLE, self::DATE_FUNCTIONS_PARAM);

        while ($i < $len) {
            // Skip whitespace
            if (ctype_space($src[$i])) {
                $i++;

                continue;
            }

            // Single-char structural tokens
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

            // Logical operators
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

            // Quoted string literal
            if ($src[$i] === '"' || $src[$i] === "'") {
                $q = $src[$i];
                $j = $i + 1;
                $val = '';
                while ($j < $len && $src[$j] !== $q) {
                    if ($src[$j] === '\\' && isset($src[$j + 1])) {
                        $j++;
                        $val .= match ($src[$j]) {
                            'n' => "\n",
                            't' => "\t",
                            '\'' => "'",
                            '"' => '"',
                            '\\' => '\\',
                            default => '\\'.$src[$j],
                        };
                        $j++;

                        continue;
                    }
                    $val .= $src[$j++];
                }
                $tokens[] = ['type' => 'VALUE', 'value' => $val];
                $i = $j + 1;

                continue;
            }

            // Comparison operators (longest match first)
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

            // Numeric literal (bare integers and decimals, including negative)
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

            // Read a bare word (stops at whitespace, structural chars, or logical symbol starts)
            $word = '';
            $allowDot = true;  // dot-notation for fields
            $allowArrow = true; // -> notation for JSON paths
            while ($i < $len && ! ctype_space($src[$i]) && ! in_array($src[$i], ['(', ')', ','], true)) {
                // Handle -> (JSON path separator — becomes part of the field name)
                if ($allowArrow && substr($src, $i, 2) === '->') {
                    $word .= '->';
                    $i += 2;

                    continue;
                }
                // Stop on && or || (logical operators)
                if ($src[$i] === '&' || $src[$i] === '|') {
                    break;
                }
                $word .= $src[$i++];
            }

            if ($word === '') {
                continue;
            }

            $lower = strtolower($word);

            // Value keywords
            if (in_array($lower, self::VALUE_KEYWORDS, true)) {
                $tokens[] = ['type' => 'VALUE', 'value' => $lower];

                continue;
            }

            // Date function call — bare word immediately followed by '('
            if (isset($src[$i]) && $src[$i] === '(' && in_array($lower, $allDateFuncs, true)) {
                $args = $this->captureParenthesized($src, $i, $len);
                $i += strlen($args);
                $tokens[] = ['type' => 'DATE_FUNC', 'value' => $lower.$args];

                continue;
            }

            // Unknown function call — bare word followed by '(' but not a date func
            if (isset($src[$i]) && $src[$i] === '(') {
                $this->invalid("Unknown function: {$lower}()");
            }

            // @-prefixed system variable
            if (str_starts_with($word, '@')) {
                $tokens[] = ['type' => 'SYSVAR', 'value' => $word];

                continue;
            }

            // Everything else is a FIELD identifier
            if (! $this->allowUnknownFields && ! in_array($word, $this->allowedFields, true)) {
                $this->invalid("Unknown field or variable: {$word}");
            }

            $tokens[] = ['type' => 'FIELD', 'value' => $word];
        }

        return $tokens;
    }

    /**
     * Capture a parenthesized argument list starting at position $i in $src.
     * Returns the captured string including outer parentheses.
     */
    private function captureParenthesized(string $src, int &$i, int $len): string
    {
        $depth = 0;
        $args = '';
        while ($i < $len) {
            if ($src[$i] === '(') {
                $depth++;
            }
            if ($src[$i] === ')') {
                $depth--;
                $args .= $src[$i++];
                if ($depth === 0) {
                    break;
                }

                continue;
            }
            $args .= $src[$i++];
        }

        return $args;
    }

    private function invalid(string $message): never
    {
        throw new InvalidRuleExpressionException($message);
    }

    // ── Static accessors for constants (used by RuleEngine / QueryFilter) ────

    /** @return list<string> */
    public static function getOperators(): array
    {
        return self::OPERATORS;
    }

    /** @return list<string> */
    public static function getValueKeywords(): array
    {
        return self::VALUE_KEYWORDS;
    }

    /** @return list<string> */
    public static function getSimpleDateFunctions(): array
    {
        return self::DATE_FUNCTIONS_SIMPLE;
    }

    /** @return list<string> */
    public static function getParamDateFunctions(): array
    {
        return self::DATE_FUNCTIONS_PARAM;
    }
}
