<?php

declare(strict_types=1);

namespace Veloquent\Core\Domain\RuleEngine\Evaluators;

use RuntimeException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Kevintherm\Exprc\Evaluators\InMemoryEvaluator;
use Veloquent\Core\Domain\Collections\Models\Collection;

/**
 * Extends exprc's InMemoryEvaluator to support Veloquent-specific features:
 *  - Sysvar resolution from a context array (@user.id, @request.body.foo)
 *  - Cross-collection EXISTS queries (@collection.name.field)
 *  - Date-suffix fields (field__date, field__year, etc.) processed via Carbon
 *  - HASKEY operator (array_key_exists)
 *
 * Note: Cross-collection lookups perform real database queries. This is intentional
 * as evaluate() is primarily used for API rule evaluation in the application layer.
 */
class UnifiedInMemoryEvaluator extends InMemoryEvaluator
{
    public function __construct(private readonly array $context)
    {
        parent::__construct($context);
    }

    // ── Overrides ──────────────────────────────────────────────────────────────

    protected function evaluateComparison(ComparisonNode $node): bool
    {
        $field = $node->field;
        $op = strtoupper($node->operator);
        $val = $node->value;

        $leftIsCollection = $this->isCollectionSysvar($field);

        $rightIsCollection = false;
        $rightPath = null;
        if ($val instanceof IdentifierNode) {
            $rightIsCollection = $this->isCollectionSysvar($val->name);
            $rightPath = $rightIsCollection ? $this->collectionPath($val->name) : null;
            $rightValue = $this->isSysvar($val->name) ? $this->sysvarValue($val->name) : data_get($this->context, $val->name);
        } else {
            $rightValue = $val;
        }

        // Cross-collection
        if ($leftIsCollection) {
            return $this->evaluateCrossCollectionExists($this->collectionPath($field), $op, $rightValue);
        }

        if ($rightIsCollection) {
            $leftValue = $this->isSysvar($field) ? $this->sysvarValue($field) : data_get($this->context, $field);

            return $this->evaluateCrossCollectionExists($rightPath, $this->invertOrdered($op), $leftValue);
        }

        $left = $this->getContextValue($field);

        $expected = $rightValue;
        if (is_array($expected)) {
            $expected = array_map(function ($val) {
                if ($val instanceof IdentifierNode) {
                    return $this->isSysvar($val->name) ? $this->sysvarValue($val->name) : data_get($this->context, $val->name);
                }

                return $val;
            }, $expected);
        } elseif ($expected instanceof IdentifierNode) {
            $expected = $this->isSysvar($expected->name) ? $this->sysvarValue($expected->name) : data_get($this->context, $expected->name);
        }

        return $this->applyOperator($left, $op, $expected);
    }

    protected function evaluateNullComparison(NullComparisonNode $node): bool
    {
        if ($this->isCollectionSysvar($node->field)) {
            $parts = explode('.', $this->collectionPath($node->field), 2);
            if (count($parts) < 2) {
                return false;
            }

            $collection = Collection::findByNameCached($parts[0]);
            if (! $collection) {
                return false;
            }

            return DB::table($collection->getPhysicalTableName())
                ->where($parts[1], $node->isNot ? '!=' : '=', null)
                ->exists();
        }

        $val = $this->getContextValue($node->field);

        return $node->isNot ? ! is_null($val) : is_null($val);
    }

    // ── Context / sysvar helpers ───────────────────────────────────────────────

    private function getContextValue(string $field): mixed
    {
        if (preg_match('/^(.+)__(date|year|month|day|time)$/i', $field, $m)) {
            $baseField = $m[1];
            $function = strtolower($m[2]);
            $value = $this->isSysvar($baseField) ? $this->sysvarValue($baseField) : data_get($this->context, $baseField);

            if ($value === null) {
                return null;
            }

            try {
                $carbon = Carbon::parse($value);

                return match ($function) {
                    'date'  => $carbon->toDateString(),
                    'year'  => $carbon->year,
                    'month' => $carbon->month,
                    'day'   => $carbon->day,
                    'time'  => $carbon->toTimeString(),
                    default => $value,
                };
            } catch (\Throwable) {
                return $value;
            }
        }

        return $this->isSysvar($field) ? $this->sysvarValue($field) : data_get($this->context, $field);
    }

    private function isSysvar(string $field): bool
    {
        return str_starts_with($field, '@') || str_starts_with($field, '__numeric__');
    }

    private function isCollectionSysvar(mixed $field): bool
    {
        return is_string($field) && str_starts_with($field, '@collection.');
    }

    private function collectionPath(string $field): string
    {
        return substr($field, strlen('@collection.'));
    }

    private function sysvarValue(mixed $field): mixed
    {
        if (! is_string($field)) {
            return $field;
        }

        if (str_starts_with($field, '__numeric__')) {
            $val = substr($field, strlen('__numeric__'));

            return is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val;
        }

        $path = ltrim($field, '@');

        return data_get($this->context, $path);
    }

    private function evaluateCrossCollectionExists(string $path, string $operator, mixed $otherValue): bool
    {
        $parts = explode('.', $path, 2);
        if (count($parts) < 2) {
            return false;
        }

        $collection = Collection::findByNameCached($parts[0]);
        if (! $collection) {
            throw new RuntimeException(sprintf('Collection "%s" not found for cross-collection lookup.', $parts[0]));
        }

        $query = DB::table($collection->getPhysicalTableName());

        if ($operator === 'IN') {
            $query->whereIn($parts[1], is_array($otherValue) ? $otherValue : [$otherValue]);
        } elseif ($operator === 'NOT IN') {
            $query->whereNotIn($parts[1], is_array($otherValue) ? $otherValue : [$otherValue]);
        } elseif ($operator === 'CONTAINS') {
            $query->whereJsonContains($parts[1], $otherValue);
        } elseif ($operator === 'NOT CONTAINS') {
            $query->whereJsonContains($parts[1], $otherValue, 'and', true);
        } elseif ($operator === 'HASKEY') {
            $query->whereJsonContainsKey($parts[1] . '->' . $otherValue);
        } elseif ($operator === 'NOT HASKEY') {
            $query->whereJsonContainsKey($parts[1] . '->' . $otherValue, 'and', true);
        } else {
            $query->where($parts[1], $operator, $otherValue);
        }

        return $query->exists();
    }

    private function invertOrdered(string $op): string
    {
        return match (strtoupper($op)) {
            '>' => '<', '<' => '>', '>=' => '<=', '<=' => '>=',
            default => $op,
        };
    }

    /**
     * Extend parent's applyOperator to handle:
     *  - HASKEY (Veloquent-specific operator)
     *  - String comparisons for date/datetime values (parent only handles numeric for >, <, etc.)
     *
     * @param string|int|float|bool|null|array<int, mixed>|\Kevintherm\Exprc\Ast\Node $expected
     */
    protected function applyOperator(mixed $actual, string $operator, string|int|float|bool|null|array|\Kevintherm\Exprc\Ast\Node $expected): bool
    {
        if ($operator === 'HASKEY') {
            return is_array($actual) && array_key_exists((string) $expected, $actual);
        }
        
        if ($operator === 'CONTAINS' && is_string($actual) && is_string($expected) && (str_contains($expected, '%') || str_contains($expected, '_'))) {
            return $this->likeMatch($actual, $expected);
        }

        if (
            in_array($operator, ['>', '>=', '<', '<='], true)
            && is_string($actual)
            && is_string($expected)
            && ! is_numeric($actual)
            && ! is_numeric($expected)
        ) {
            $cmp = strcmp($actual, $expected);

            return match ($operator) {
                '>'  => $cmp > 0,
                '>=' => $cmp >= 0,
                '<'  => $cmp < 0,
                '<=' => $cmp <= 0,
            };
        }

        return parent::applyOperator($actual, $operator, $expected);
    }
}
