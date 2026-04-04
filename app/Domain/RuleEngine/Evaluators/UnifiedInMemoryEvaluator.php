<?php

declare(strict_types=1);

namespace App\Domain\RuleEngine\Evaluators;

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

        // Resolve LHS
        $left = $this->isSysvar($field) ? $this->getSysvarValue($field) : data_get($this->context, $field);

        // Resolve RHS
        if ($val instanceof IdentifierNode) {
            $right = $val->accept($this);
        } else {
            $right = $val;
        }

        return $this->compare($left, $op, $right);
    }

    public function visitNullComparisonNode(NullComparisonNode $node): bool
    {
        $val = $this->isSysvar($node->field)
            ? $this->getSysvarValue($node->field)
            : data_get($this->context, $node->field);

        return $node->isNot ? ! is_null($val) : is_null($val);
    }

    public function visitIdentifierNode(IdentifierNode $node): mixed
    {
        return $this->isSysvar($node->name) ? $this->getSysvarValue($node->name) : data_get($this->context, $node->name);
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
