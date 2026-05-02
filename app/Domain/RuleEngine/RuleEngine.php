<?php

namespace App\Domain\RuleEngine;

use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\Records\Services\RelationJoinResolver;
use App\Domain\RuleEngine\Evaluators\UnifiedEvaluator;
use App\Domain\RuleEngine\Evaluators\UnifiedInMemoryEvaluator;
use App\Domain\RuleEngine\Resolvers\UnifiedFieldResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Ast\LogicalNode;
use Kevintherm\Exprc\Ast\Node;
use Kevintherm\Exprc\Ast\NotNode;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Kevintherm\Exprc\Exprc;
use Throwable;

class RuleEngine
{
    protected array $allowedFields = [];

    protected ?RelationJoinResolver $joinResolver = null;

    protected ?Builder $query = null;

    public function __construct() {}

    public static function make(array $allowedFields = []): static
    {
        $instance = new static;
        $instance->allowedFields = $allowedFields;

        return $instance;
    }

    public static function for(Builder $query, array $allowedFields = []): static
    {
        $instance = new static;
        $instance->query = $query;
        $instance->allowedFields = $allowedFields;

        return $instance;
    }

    public function withRelationJoinResolver(RelationJoinResolver $resolver): static
    {
        $this->joinResolver = $resolver;

        return $this;
    }

    public function withQueryFieldAdapter(mixed $adapter): static
    {
        return $this;
    }

    public function lint(?string $filter, bool $inMemory = false): void
    {
        $filter = trim($filter ?? '');
        if ($filter === '') {
            return;
        }

        try {
            $ast = (new Exprc)->parse($this->normalize($filter));
            $resolver = (new UnifiedFieldResolver);
            $this->validateAst($ast, $resolver);
        } catch (Throwable $e) {
            throw new InvalidRuleExpressionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function validateAst(Node $node, UnifiedFieldResolver $resolver): void
    {
        if ($node instanceof LogicalNode) {
            $this->validateAst($node->left, $resolver);
            $this->validateAst($node->right, $resolver);

            return;
        }

        if ($node instanceof NotNode) {
            $this->validateAst($node->node, $resolver);

            return;
        }

        if ($node instanceof NullComparisonNode) {
            $field = $node->field;
            $fieldForValidation = preg_replace('/__(date|year|month|day|time)$/i', '', $field);

            if (! str_starts_with($fieldForValidation, '__sysvar__') && ! str_starts_with($fieldForValidation, '__numeric__')) {
                if (! empty($this->allowedFields) && ! in_array($fieldForValidation, $this->allowedFields)) {
                    throw new \RuntimeException(sprintf('Unknown field "%s"', $fieldForValidation));
                }
            }

            return;
        }

        if ($node instanceof ComparisonNode) {
            $field = $node->field;
            $fieldForValidation = preg_replace('/__(date|year|month|day|time)$/i', '', $field);

            // Validate LHS if it's not a pseudo-sysvar or numeric
            if (! str_starts_with($fieldForValidation, '__sysvar__') && ! str_starts_with($fieldForValidation, '__numeric__')) {
                if (! empty($this->allowedFields) && ! in_array($fieldForValidation, $this->allowedFields)) {
                    throw new \RuntimeException(sprintf('Unknown field "%s"', $fieldForValidation));
                }
            }

            // If LHS is a sysvar, validate its prefix
            if (str_starts_with($fieldForValidation, '__sysvar__')) {
                $path = str_replace('__sysvar__', '', $fieldForValidation);
                if (! preg_match('/^(request|user|auth|collection)\./', $path)) {
                    throw new \RuntimeException(sprintf('Invalid system variable namespace: %s', $path));
                }
            }

            // Validate RHS if it's an IdentifierNode
            if ($node->value instanceof IdentifierNode) {
                $name = $node->value->name;
                if (str_starts_with($name, '__sysvar__')) {
                    $path = str_replace('__sysvar__', '', $name);
                    if (! preg_match('/^(request|user|auth|collection)\./', $path)) {
                        throw new \RuntimeException(sprintf('Invalid system variable namespace: %s', $path));
                    }
                } elseif (! str_starts_with($name, '__numeric__')) {
                    if (! empty($this->allowedFields) && ! in_array($name, $this->allowedFields)) {
                        throw new \RuntimeException(sprintf('Unknown field "%s"', $name));
                    }
                }
            }
        }
    }

    public function evaluate(string $filter, array $context = []): bool
    {
        $filter = trim($filter);
        if ($filter === '') {
            return true;
        }

        try {
            $exprc = new Exprc;
            $ast = $exprc->parse($this->normalize($filter));

            // Map operator tokens back to JSON ops for the evaluator
            $ast = $this->restoreOperators($ast, $filter);

            $evaluator = (new UnifiedInMemoryEvaluator($context));

            return (bool) $evaluator->evaluate($ast);
        } catch (Throwable $e) {
            throw new InvalidRuleExpressionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function run(string $filter, array $context = []): Builder
    {
        if (! $this->query) {
            throw new \RuntimeException('Query builder must be set via for() before calling run().');
        }

        $filter = trim($filter);
        if ($filter === '') {
            return $this->query;
        }

        try {
            $normalized = $this->normalize($filter);

            $exprc = new Exprc;
            $ast = $exprc->parse($normalized);

            // Map operators and restore arrow
            $ast = $this->restoreOperators($ast, $filter);

            $resolver = (new UnifiedFieldResolver)->withJoinResolver($this->joinResolver);
            if ($this->query instanceof Builder) {
                $resolver->setQuery($this->query);
            }
            $evaluator = (new UnifiedEvaluator($this->query, $resolver))->withSysvars($context);

            $evaluator->evaluate($ast);
        } catch (Throwable $e) {
            throw new InvalidRuleExpressionException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $this->query;
    }

    private function restoreOperators(Node $node, string $originalFilter): Node
    {
        if ($node instanceof LogicalNode) {
            return new LogicalNode(
                $node->operator,
                $this->restoreOperators($node->left, $originalFilter),
                $this->restoreOperators($node->right, $originalFilter)
            );
        }

        if ($node instanceof NotNode) {
            return new NotNode($this->restoreOperators($node->node, $originalFilter));
        }

        if ($node instanceof NullComparisonNode) {
            return new NullComparisonNode(
                str_replace('_ARROW_', '->', $node->field),
                $node->isNot
            );
        }

        if ($node instanceof ComparisonNode) {
            $field = str_replace('_ARROW_', '->', $node->field);
            $operator = strtoupper($node->operator);

            // Handle implicit null conversion: field = null -> IS NULL, field != null -> IS NOT NULL
            if ($node->value === null) {
                if ($operator === '=') {
                    return new NullComparisonNode($field, false);
                }

                if ($operator === '!=' || $operator === '<>') {
                    return new NullComparisonNode($field, true);
                }
            }

            // Check original operator
            $originalLower = strtolower($originalFilter);

            if ($operator === 'LIKE' && str_contains($originalLower, '?=')) {
                $operator = 'CONTAINS';
            } elseif ($operator === 'NOT LIKE' && str_contains($originalLower, '?&')) {
                $operator = 'HASKEY';
            }

            return new ComparisonNode($field, $operator, $node->value);
        }

        return $node;
    }

    protected function normalize(string $filter): string
    {
        $filter = str_replace(['&&', '||'], ['AND', 'OR'], $filter);
        $filter = str_ireplace(' in ', ' IN ', $filter);
        $filter = str_ireplace(' not in ', ' NOT IN ', $filter);
        $filter = str_replace('?=', ' LIKE ', $filter);
        $filter = str_replace('?&', ' NOT LIKE ', $filter);

        // Normalize date functions: date(field) -> field__date
        $filter = preg_replace('/(date|year|month|day|time)\(([^)]+)\)/i', '$2__$1', $filter);

        // Evaluate dynamic date functions: daysago(30) -> "2024-04-02 12:00:00"
        $filter = $this->normalizeDynamicDateFunctions($filter);

        $filter = str_replace('->', '_ARROW_', $filter); // Replace -> with something that looks like an underscore + arrow for lexer
        $filter = preg_replace('/@([A-Za-z0-9_.]+)/', '__sysvar__$1', $filter);

        if (preg_match('/^\s*(\d+)\s*(>|<|>=|<=|=)/', $filter, $matches)) {
            $filter = preg_replace('/^\s*(\d+)/', '__numeric__$1', $filter);
        }

        return $filter;
    }

    private function normalizeDynamicDateFunctions(string $filter): string
    {
        $simple = Tokenizer::getSimpleDateFunctions();
        $param = Tokenizer::getParamDateFunctions();

        // Handle parameterized functions first to avoid partial matches if names overlap
        $paramPattern = '/\b('.implode('|', $param).')\((\d+)\)/i';
        $filter = preg_replace_callback($paramPattern, function ($matches) {
            return '"'.$this->evaluateDateFunction($matches[1], (int) $matches[2]).'"';
        }, $filter);

        // Handle simple functions
        $simplePattern = '/\b('.implode('|', $simple).')\(\)/i';
        $filter = preg_replace_callback($simplePattern, function ($matches) {
            return '"'.$this->evaluateDateFunction($matches[1]).'"';
        }, $filter);

        return $filter;
    }

    private function evaluateDateFunction(string $name, ?int $value = null): string
    {
        $now = Carbon::now();

        return match (strtolower($name)) {
            'now' => $now->toDateTimeString(),
            'today' => $now->toDateString(),
            'yesterday' => $now->subDay()->toDateString(),
            'tomorrow' => $now->addDay()->toDateString(),
            'thisweek' => $now->startOfWeek()->toDateString(),
            'lastweek' => $now->subWeek()->startOfWeek()->toDateString(),
            'nextweek' => $now->addWeek()->startOfWeek()->toDateString(),
            'thismonth' => $now->startOfMonth()->toDateString(),
            'lastmonth' => $now->subMonth()->startOfMonth()->toDateString(),
            'nextmonth' => $now->addMonth()->startOfMonth()->toDateString(),
            'thisyear' => $now->startOfYear()->toDateString(),
            'lastyear' => $now->subYear()->startOfYear()->toDateString(),
            'nextyear' => $now->addYear()->startOfYear()->toDateString(),
            'startofday' => $now->startOfDay()->toDateTimeString(),
            'endofday' => $now->endOfDay()->toDateTimeString(),
            'startofweek' => $now->startOfWeek()->toDateTimeString(),
            'endofweek' => $now->endOfWeek()->toDateTimeString(),
            'startofmonth' => $now->startOfMonth()->toDateTimeString(),
            'endofmonth' => $now->endOfMonth()->toDateTimeString(),
            'startofyear' => $now->startOfYear()->toDateTimeString(),
            'endofyear' => $now->endOfYear()->toDateTimeString(),
            'daysago' => $now->subDays($value)->toDateTimeString(),
            'daysfromnow' => $now->addDays($value)->toDateTimeString(),
            'weeksago' => $now->subWeeks($value)->toDateTimeString(),
            'weeksfromnow' => $now->addWeeks($value)->toDateTimeString(),
            'monthsago' => $now->subMonths($value)->toDateTimeString(),
            'monthsfromnow' => $now->addMonths($value)->toDateTimeString(),
            'yearsago' => $now->subYears($value)->toDateTimeString(),
            'yearsfromnow' => $now->addYears($value)->toDateTimeString(),
            default => throw new \RuntimeException("Unsupported date function: {$name}"),
        };
    }
}
