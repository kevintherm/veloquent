<?php

declare(strict_types=1);

namespace App\Domain\RuleEngine\Evaluators;

use App\Domain\Collections\Models\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Ast\LogicalNode;
use Kevintherm\Exprc\Ast\Node;
use Kevintherm\Exprc\Ast\NotNode;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Kevintherm\Exprc\Ast\VisitorInterface;
use Kevintherm\Exprc\EvaluatorInterface;
use RuntimeException;

class UnifiedInMemoryEvaluator implements EvaluatorInterface, VisitorInterface
{
    public function __construct(private readonly array $context) {}

    private function isSysvar(string $field): bool
    {
        return str_starts_with($field, '__sysvar__') || str_starts_with($field, '__numeric__');
    }

    private function getSysvarValue(mixed $field): mixed
    {
        if (! is_string($field)) {
            return $field;
        }

        if (str_starts_with($field, '__numeric__')) {
            $val = str_replace('__numeric__', '', $field);

            return is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val;
        }

        $path = str_replace('__sysvar__', '', $field);

        return data_get($this->context, $path);
    }

    public function evaluate(Node $node): mixed
    {
        return $node->accept($this);
    }

    public function beforeProcessNode(Node $node): void {}

    public function afterProcessNode(Node $node, mixed $result): void {}

    public function visitLogicalNode(LogicalNode $node): bool
    {
        $left = $node->left->accept($this);
        $right = $node->right->accept($this);
        $op = strtoupper($node->operator);

        if ($op === 'AND') {
            return (bool) ($left && $right);
        }
        if ($op === 'OR') {
            return (bool) ($left || $right);
        }

        throw new RuntimeException("Unsupported logical operator: $op");
    }

    public function visitNotNode(NotNode $node): bool
    {
        return ! (bool) $node->node->accept($this);
    }

    public function visitComparisonNode(ComparisonNode $node): bool
    {
        $field = $node->field;
        $op = strtoupper($node->operator);
        $val = $node->value;

        // Check if either side is a cross collection subquery
        $leftIsCollection = is_string($field) && str_starts_with($field, '__sysvar__collection.');

        $rightIsCollection = false;
        if ($val instanceof IdentifierNode) {
            $rightValue = $this->isSysvar($val->name) ? $this->getSysvarValue($val->name) : data_get($this->context, $val->name);
            if (str_starts_with($val->name, '__sysvar__collection.')) {
                $rightIsCollection = true;
                $rightPath = str_replace('__sysvar__collection.', '', $val->name);
            }
        } else {
            $rightValue = $val;
        }

        if ($leftIsCollection) {
            $leftPath = str_replace('__sysvar__collection.', '', $field);

            return $this->evaluateCrossCollectionExists($leftPath, $op, $rightValue);
        }

        if ($rightIsCollection) {
            // invert operator and query the rightPath against LHS value
            $leftValue = $this->isSysvar($field) ? $this->getSysvarValue($field) : data_get($this->context, $field);

            return $this->evaluateCrossCollectionExists($rightPath, $this->invertOperatorForInMemory($op), $leftValue);
        }

        // Original logic
        $left = $this->getValueWithSuffix($field);

        return $this->compare($left, $op, $rightValue);
    }

    public function visitNullComparisonNode(NullComparisonNode $node): bool
    {
        if (str_starts_with($node->field, '__sysvar__collection.')) {
            $path = str_replace('__sysvar__collection.', '', $node->field);
            $parts = explode('.', $path, 2);
            if (count($parts) < 2) {
                return false;
            }
            $collection = Collection::findByNameCached($parts[0]);
            if (! $collection) {
                return false;
            }
            $tableName = $collection->getPhysicalTableName();

            return DB::table($tableName)
                ->where($parts[1], $node->isNot ? '!=' : '=', null)
                ->exists();
        }

        $val = $this->getValueWithSuffix($node->field);

        return $node->isNot ? ! is_null($val) : is_null($val);
    }

    public function visitIdentifierNode(IdentifierNode $node): mixed
    {
        return $this->getValueWithSuffix($node->name);
    }

    private function getValueWithSuffix(string $field): mixed
    {
        if (preg_match('/^(.+)__(date|year|month|day|time)$/i', $field, $matches)) {
            $baseField = $matches[1];
            $function = strtolower($matches[2]);
            $value = $this->isSysvar($baseField) ? $this->getSysvarValue($baseField) : data_get($this->context, $baseField);

            if ($value === null) {
                return null;
            }

            try {
                $carbon = Carbon::parse($value);

                return match ($function) {
                    'date' => $carbon->toDateString(),
                    'year' => $carbon->year,
                    'month' => $carbon->month,
                    'day' => $carbon->day,
                    'time' => $carbon->toTimeString(),
                    default => $value,
                };
            } catch (\Throwable) {
                return $value;
            }
        }

        return $this->isSysvar($field) ? $this->getSysvarValue($field) : data_get($this->context, $field);
    }

    private function evaluateCrossCollectionExists(string $path, string $operator, mixed $otherValue): bool
    {
        $parts = explode('.', $path, 2);
        if (count($parts) < 2) {
            return false;
        }

        $collectionName = $parts[0];
        $collectionField = $parts[1];

        $collection = Collection::findByNameCached($collectionName);
        if (! $collection) {
            return false;
        }

        $tableName = $collection->getPhysicalTableName();
        $query = DB::table($tableName);

        if ($operator === 'IN') {
            $query->whereIn($collectionField, is_array($otherValue) ? $otherValue : [$otherValue]);
        } elseif ($operator === 'NOT IN') {
            $query->whereNotIn($collectionField, is_array($otherValue) ? $otherValue : [$otherValue]);
        } else {
            $sqlOp = match ($operator) {
                '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE' => $operator,
                'CONTAINS', 'HASKEY' => throw new RuntimeException("Operator $operator not supported in cross collections."),
                default => '=',
            };
            $query->where($collectionField, $sqlOp, $otherValue);
        }

        return $query->exists();
    }

    private function invertOperatorForInMemory(string $op): string
    {
        return match (strtoupper($op)) {
            '>' => '<',
            '<' => '>',
            '>=' => '<=',
            '<=' => '>=',
            default => $op,
        };
    }

    private function compare(mixed $l, string $op, mixed $r): bool
    {
        return match ($op) {
            '=' => $l == $r,
            '!=' => $l != $r,
            '>' => $l > $r,
            '>=' => $l >= $r,
            '<' => $l < $r,
            '<=' => $l <= $r,
            'LIKE' => str_contains((string) ($l ?? ''), str_replace('%', '', (string) ($r ?? ''))),
            'NOT LIKE' => ! str_contains((string) ($l ?? ''), str_replace('%', '', (string) ($r ?? ''))),
            'CONTAINS' => is_array($l) && in_array($r, $l),
            'HASKEY' => is_array($l) && array_key_exists($r, $l),
            'IN' => is_array($r) && in_array($l, $r, false),
            'NOT IN' => is_array($r) && ! in_array($l, $r, false),
            default => false
        };
    }
}
