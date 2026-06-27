<?php

namespace Veloquent\Core\Domain\RuleEngine;

use Throwable;
use RuntimeException;
use Kevintherm\Exprc\Ast\Node;
use Kevintherm\Exprc\Ast\NotNode;
use Kevintherm\Exprc\Ast\LogicalNode;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Illuminate\Database\Eloquent\Builder;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Services\RelationJoinResolver;
use Veloquent\Core\Domain\RuleEngine\Evaluators\UnifiedEvaluator;
use Veloquent\Core\Domain\RuleEngine\Resolvers\UnifiedFieldResolver;
use Veloquent\Core\Domain\RuleEngine\Evaluators\UnifiedInMemoryEvaluator;
use Veloquent\Core\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;

class RuleEngine
{
    protected array $allowedFields = [];

    protected ?RelationJoinResolver $joinResolver = null;

    protected ?Builder $query = null;

    final public function __construct() {}

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

    /**
     * Parse and validate a rule expression without executing it.
     * Throws InvalidRuleExpressionException on syntax or field validation errors.
     * @throws InvalidRuleExpressionException
     */
    public function lint(?string $rule): void
    {
        if ($rule === null || trim($rule) === '') {
            return;
        }

        try {
            $ast = (new VeloquentParser)->parse($rule);
            $this->validateAst($ast);
        } catch (Throwable $e) {
            throw new InvalidRuleExpressionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Evaluate a rule expression against a context in-memory.
     * Throws InvalidRuleExpressionException on syntax or field validation errors.
     */
    public function evaluate(?string $rule, array $context = []): bool
    {
        if ($rule === null || trim($rule) === '') {
            return true;
        }

        try {
            $ast = (new VeloquentParser)->parse($rule);
            $this->validateAst($ast);

            return (new UnifiedInMemoryEvaluator($context))
                ->evaluate($ast);
        } catch (Throwable $e) {
            throw new InvalidRuleExpressionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Apply a filter expression as WHERE clauses on the query builder.
     */
    public function run(?string $rule, array $context = []): Builder
    {
        if (! $this->query) {
            throw new RuntimeException('Query builder must be set via for() before calling run().');
        }

        if ($rule === null) {
            return $this->query;
        }

        $rule = trim($rule);
        if ($rule === '') {
            return $this->query;
        }

        try {
            $ast = (new VeloquentParser)->parse($rule);
            $this->validateAst($ast);

            $resolver = (new UnifiedFieldResolver)->withJoinResolver($this->joinResolver);
            if ($this->query instanceof Builder) {
                $resolver->setQuery($this->query);
            }

            (new UnifiedEvaluator($this->query, $resolver))
                ->withSysvars($context)
                ->evaluate($ast);
        } catch (Throwable $e) {
            throw new InvalidRuleExpressionException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $this->query;
    }

    // ── AST Validation (for lint()) ────────────────────────────────────────────

    private function validateAst(Node $node): void
    {
        if ($node instanceof LogicalNode) {
            $this->validateAst($node->left);
            $this->validateAst($node->right);

            return;
        }

        if ($node instanceof NotNode) {
            $this->validateAst($node->node);

            return;
        }

        if ($node instanceof NullComparisonNode) {
            $this->validateField($node->field);

            return;
        }

        if ($node instanceof ComparisonNode) {
            $this->validateField($node->field);

            if ($node->value instanceof IdentifierNode) {
                $this->validateField($node->value->name);
            }
        }
    }

    /**
     * Validates a field reference used in an expression.
     *
     * Supported field formats:
     * - Regular fields (e.g. `name`, `user.email`)
     * - System variables prefixed with `@`
     *   (`@request.*`, `@user.*`, `@auth.*`, `@collection.*`)
     * - Internal numeric placeholders prefixed with `__numeric__`
     *
     * For `@collection` references, the method:
     * - Removes any bracket filter (e.g. `users[id=@request.body.client].coach`
     *   becomes `users.coach`) so the collection/field structure can be validated.
     * - Verifies that the reference follows the
     *   `@collection.name.field` or `@collection.name[filter].field` format.
     * - Ensures the referenced collection exists.
     *
     * For normal fields, the root field name must exist in `$this->allowedFields`
     * when field restrictions are enabled.
     *
     * @throws RuntimeException If the field namespace, collection reference,
     *                          collection, or field name is invalid.
     */
    private function validateField(string $field): void
    {
        $base = preg_replace('/__(date|year|month|day|time)$/i', '', $field);

        if (str_starts_with($base, '@') || str_starts_with($base, '__numeric__')) {
            if (str_starts_with($base, '@')) {
                $path = ltrim($base, '@');
                if (! preg_match('/^(request|user|auth|collection)\./', $path)) {
                    throw new RuntimeException(sprintf('Invalid system variable namespace: %s', $path));
                }

                if (str_starts_with($path, 'collection.')) {
                    $collectionPath = substr($path, strlen('collection.'));

                    $normalizedPath = preg_replace('/\[[^\]]*\]/', '', $collectionPath);

                    $parts = explode('.', $normalizedPath ?? $collectionPath, 2);
                    if (count($parts) < 2) {
                        throw new RuntimeException(
                            'Invalid collection sysvar format. Use @collection.name.field or @collection.name[filter].field'
                        );
                    }

                    $collection = Collection::findByNameCached($parts[0]);
                    if (! $collection) {
                        throw new RuntimeException(
                            sprintf('Collection "%s" not found for cross-collection lookup.', $parts[0])
                        );
                    }
                }
            }

            return;
        }

        $root = explode('.', $base)[0];
        if (! empty($this->allowedFields) && ! in_array($root, $this->allowedFields)) {
            throw new RuntimeException(sprintf('Unknown field "%s"', $root));
        }
    }
}
