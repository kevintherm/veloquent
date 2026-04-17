<?php

declare(strict_types=1);

namespace App\Domain\RuleEngine\Evaluators;

use App\Domain\Collections\Models\Collection;
use Illuminate\Database\Eloquent\Builder;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Ast\LogicalNode;
use Kevintherm\Exprc\Ast\Node;
use Kevintherm\Exprc\Ast\NotNode;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Kevintherm\Exprc\Ast\VisitorInterface;
use Kevintherm\Exprc\EvaluatorInterface;
use Kevintherm\Exprc\Resolvers\FieldResolverInterface;
use RuntimeException;

class UnifiedEvaluator implements EvaluatorInterface, VisitorInterface
{
    private array $sysvars = [];

    public function __construct(
        private readonly object $builder,
        private readonly FieldResolverInterface $resolver
    ) {}

    public function withSysvars(array $sysvars): self
    {
        $this->sysvars = $sysvars;

        return $this;
    }

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

        return data_get($this->sysvars, $path);
    }

    public function evaluate(Node $node): mixed
    {
        $node->accept($this);

        return $this->builder;
    }

    public function beforeProcessNode(Node $node): void
    {
        // Lifecycle hook
    }

    public function afterProcessNode(Node $node, mixed $result): void
    {
        // Lifecycle hook
    }

    public function visitLogicalNode(LogicalNode $node): mixed
    {
        $operator = strtoupper($node->operator);

        if ($operator === 'AND') {
            $this->applyNestedWhere($this->builder, 'and', function (object $nested) use ($node): void {
                $this->applyNodeInContext($nested, $node->left, 'and');
                $this->applyNodeInContext($nested, $node->right, 'and');
            });

            return null;
        }

        if ($operator === 'OR') {
            $this->applyNestedWhere($this->builder, 'and', function (object $nested) use ($node): void {
                $this->applyNodeInContext($nested, $node->left, 'and');
                $this->applyNodeInContext($nested, $node->right, 'or');
            });

            return null;
        }

        throw new RuntimeException(sprintf('Unsupported logical operator "%s".', $node->operator));
    }

    public function visitNotNode(NotNode $node): mixed
    {
        $this->builder->where(function ($query) use ($node) {
            $this->applyNodeInContext($query, $node->node, 'and');
        }, null, null, 'and not');

        return null;
    }

    public function visitComparisonNode(ComparisonNode $node): mixed
    {
        $this->applyComparisonNode($this->builder, $node, 'and');

        return null;
    }

    public function visitNullComparisonNode(NullComparisonNode $node): mixed
    {
        $this->applyNullComparison($this->builder, $node->field, $node->isNot, 'and');

        return null;
    }

    public function visitIdentifierNode(IdentifierNode $node): mixed
    {
        return $this->isSysvar($node->name) ? $this->getSysvarValue($node->name) : $node->name;
    }

    private function applyNodeInContext(object $builder, Node $node, string $boolean): void
    {
        if ($node instanceof ComparisonNode) {
            $this->applyComparisonNode($builder, $node, $boolean);

            return;
        }

        if ($node instanceof NullComparisonNode) {
            $this->applyNullComparison($builder, $node->field, $node->isNot, $boolean);

            return;
        }

        if ($node instanceof LogicalNode || $node instanceof NotNode) {
            if ($boolean === 'or') {
                $builder->orWhere(function ($query) use ($node) {
                    $node->accept($this->replicate($query));
                });
            } else {
                $node->accept($this->replicate($builder));
            }

            return;
        }
    }

    private function replicate(object $builder): self
    {
        return (new self($builder, $this->resolver))->withSysvars($this->sysvars);
    }

    private function applyComparisonNode(object $builder, ComparisonNode $node, string $boolean): void
    {
        $field = $node->field;
        $operator = strtoupper($node->operator);
        $value = $node->value;

        $leftIsCollectionSysvar = is_string($field) && str_starts_with($field, '__sysvar__collection.');

        $rightIsCollectionSysvar = false;
        if ($value instanceof IdentifierNode) {
            $rightValue = $this->isSysvar($value->name) ? $this->getSysvarValue($value->name) : $value->name;
            $rightIsSysvar = $this->isSysvar($value->name);
            $rightIsField = ! $rightIsSysvar;
            $rightIsCollectionSysvar = str_starts_with($value->name, '__sysvar__collection.');
        } else {
            $rightValue = $value;
            $rightIsSysvar = false;
            $rightIsField = false;
        }

        // Handle cross-collection lookups
        if ($leftIsCollectionSysvar) {
            $collectionPath = str_replace('__sysvar__collection.', '', $field);
            $this->applyCrossCollectionComparison($builder, $collectionPath, $operator, $rightValue, $rightIsField, $boolean);

            return;
        }

        if ($rightIsCollectionSysvar) {
            $collectionPath = str_replace('__sysvar__collection.', '', $value->name);
            $leftValue = $this->isSysvar($field) ? $this->getSysvarValue($field) : $field;
            $leftIsField = ! $this->isSysvar($field);

            $this->applyCrossCollectionComparison($builder, $collectionPath, $this->invertOrderedOperator($operator), $leftValue, $leftIsField, $boolean);

            return;
        }

        // Symmetric check
        $leftIsSysvar = $this->isSysvar($field);
        $leftValue = $leftIsSysvar ? $this->getSysvarValue($field) : null;

        // List operators (Priority)
        if ($operator === 'IN' || $operator === 'NOT IN') {
            $inValues = is_array($value) ? $value : [$value];

            $this->applyInClause($builder, $this->resolver->resolve($field), $inValues, $boolean, $operator === 'NOT IN');

            return;
        }

        if ($operator === 'CONTAINS') {
            $method = $boolean === 'or' ? 'orWhereJsonContains' : 'whereJsonContains';
            $builder->{$method}($this->resolver->resolve($field), $rightValue);

            return;
        }

        if ($operator === 'HASKEY') {
            $method = $boolean === 'or' ? 'orWhereJsonContainsKey' : 'whereJsonContainsKey';
            $builder->{$method}($this->resolver->resolve($field), $rightValue);

            return;
        }

        if ($leftIsSysvar && $rightIsField) {
            // Flip: @auth.id = id -> id = @auth.id
            $this->applyScalarComparison($builder, $this->resolver->resolve((string) $rightValue), $this->invertOrderedOperator($operator), $leftValue, $boolean);

            return;
        }

        if ($leftIsSysvar && $rightIsSysvar) {
            $this->applyLiteralComparison($builder, $operator, $leftValue, $rightValue, $boolean);

            return;
        }

        if ($leftIsSysvar && ! $rightIsSysvar && ! $rightIsField) {
            $this->applyLiteralComparison($builder, $operator, $leftValue, $rightValue, $boolean);

            return;
        }

        if (! $leftIsSysvar && ! $rightIsSysvar && ! $rightIsField) {
            $this->applyScalarComparison($builder, $this->resolver->resolve($field), $operator, $rightValue, $boolean);

            return;
        }

        if (! $leftIsSysvar && $rightIsField) {
            $method = $boolean === 'or' ? 'orWhereColumn' : 'whereColumn';
            $builder->{$method}($this->resolver->resolve($field), $this->toSqlOperator($operator), $this->resolver->resolve((string) $rightValue));

            return;
        }

        // Default behavior (Field op Literal) - Fallback
        $this->applyScalarComparison($builder, $this->resolver->resolve($field), $operator, $rightValue, $boolean);
    }

    private function applyCrossCollectionComparison(object $builder, string $collectionPath, string $operator, mixed $otherValue, bool $otherIsField, string $boolean): void
    {
        $parts = explode('.', $collectionPath, 2);
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid collection sysvar.');
        }
        $collectionName = $parts[0];
        $collectionField = $parts[1];

        $collection = Collection::findByNameCached($collectionName);
        if (! $collection) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $builder->{$method}('0 = 1');

            return;
        }
        $tableName = $collection->getPhysicalTableName();

        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        $builder->{$method}(function ($q) use ($builder, $tableName, $collectionField, $operator, $otherValue, $otherIsField) {
            $q->selectRaw('1')->from($tableName);

            if ($operator === 'IN' || $operator === 'NOT IN') {
                $inValues = is_array($otherValue) ? $otherValue : [$otherValue];
                if ($operator === 'IN') {
                    $q->whereIn($tableName.'.'.$collectionField, $inValues);
                } else {
                    $q->whereNotIn($tableName.'.'.$collectionField, $inValues);
                }
            } elseif ($otherIsField) {
                // Determine the parent table name to prefix the other value
                $parentTable = $builder instanceof Builder ? $builder->getModel()->getTable() : null;
                $resolvedOtherValue = $this->resolver->resolve((string) $otherValue);
                if ($parentTable && ! str_contains($resolvedOtherValue, '.')) {
                    $resolvedOtherValue = $parentTable.'.'.$resolvedOtherValue;
                }

                $q->whereColumn($tableName.'.'.$collectionField, $this->toSqlOperator($operator), $resolvedOtherValue);
            } else {
                $q->where($tableName.'.'.$collectionField, $this->toSqlOperator($operator), $otherValue);
            }
        });
    }

    private function applyScalarComparison(object $builder, string $field, string $operator, mixed $value, string $boolean): void
    {
        $sqlOperator = $this->toSqlOperator($operator);

        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $builder->{$method}($field, $sqlOperator, $value);
    }

    private function applyNullComparison(object $builder, string $field, bool $isNot, string $boolean): void
    {
        if (str_starts_with($field, '__sysvar__collection.')) {
            $collectionPath = str_replace('__sysvar__collection.', '', $field);
            $parts = explode('.', $collectionPath, 2);
            if (count($parts) < 2) {
                throw new RuntimeException('Invalid collection sysvar.');
            }
            $collectionName = $parts[0];
            $collectionField = $parts[1];

            $collection = Collection::findByNameCached($collectionName);
            if (! $collection) {
                $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
                $builder->{$method}('0 = 1');

                return;
            }
            $tableName = $collection->getPhysicalTableName();

            $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
            $builder->{$method}(function ($q) use ($tableName, $collectionField, $isNot) {
                $q->selectRaw('1')->from($tableName);
                if ($isNot) {
                    $q->whereNotNull($tableName.'.'.$collectionField);
                } else {
                    $q->whereNull($tableName.'.'.$collectionField);
                }
            });

            return;
        }

        if ($this->isSysvar($field)) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $sql = $isNot ? '? IS NOT NULL' : '? IS NULL';
            $builder->{$method}($sql, [$this->getSysvarValue($field)]);

            return;
        }

        $resolvedField = $this->resolver->resolve($field);
        $method = $isNot
            ? ($boolean === 'or' ? 'orWhereNotNull' : 'whereNotNull')
            : ($boolean === 'or' ? 'orWhereNull' : 'whereNull');

        $builder->{$method}($resolvedField);
    }

    private function applyLiteralComparison(object $builder, string $operator, mixed $left, mixed $right, string $boolean): void
    {
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $builder->{$method}('? '.$this->toSqlOperator($operator).' ?', [$left, $right]);
    }

    private function applyInClause(object $builder, string $field, array $values, string $boolean, bool $not): void
    {
        if ($not) {
            $method = $boolean === 'or' ? 'orWhereNotIn' : 'whereNotIn';
            $builder->{$method}($field, $values);

            return;
        }

        $method = $boolean === 'or' ? 'orWhereIn' : 'whereIn';
        $builder->{$method}($field, $values);
    }

    private function applyNestedWhere(object $builder, string $boolean, callable $callback): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $builder->{$method}(function ($nested) use ($callback): void {
            $callback($nested);
        });
    }

    private function negateNode(Node $node): Node
    {
        if ($node instanceof NotNode) {
            return $node->node;
        }

        if ($node instanceof LogicalNode) {
            if (strtoupper($node->operator) === 'AND') {
                return new LogicalNode('OR', $this->negateNode($node->left), $this->negateNode($node->right));
            }

            return new LogicalNode('AND', $this->negateNode($node->left), $this->negateNode($node->right));
        }

        if ($node instanceof ComparisonNode) {
            return new ComparisonNode($node->field, $this->invertOperator($node->operator), $node->value);
        }

        throw new RuntimeException('Unknown AST node received while negating query expression.');
    }

    private function invertOperator(string $operator): string
    {
        return match (strtoupper($operator)) {
            '=' => '!=',
            '!=' => '=',
            '>' => '<=',
            '>=' => '<',
            '<' => '>=',
            '<=' => '>',
            'LIKE' => 'NOT LIKE',
            'NOT LIKE' => 'LIKE',
            'IN' => 'NOT IN',
            'NOT IN' => 'IN',
            default => throw new RuntimeException(sprintf('Unsupported operator "%s" for negation.', $operator)),
        };
    }

    private function invertOrderedOperator(string $op): string
    {
        return match (strtoupper($op)) {
            '>' => '<',
            '<' => '>',
            '>=' => '<=',
            '<=' => '>=',
            default => $op,
        };
    }

    private function toSqlOperator(string $operator): string
    {
        return match (strtoupper($operator)) {
            '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'CONTAINS', 'HASKEY', 'IN', 'NOT IN' => $operator,
            default => throw new RuntimeException(sprintf('Unsupported operator "%s" for query builder.', $operator)),
        };
    }
}
