<?php

namespace App\Domain\Records\QueryBuilder;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\QueryCompiler\Exceptions\UnsupportedQueryFeatureException;
use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use RuntimeException;

class RecordBuilder extends Builder
{
    public function applySorting(?string $sortParam): static
    {
        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $systemSorts = ['created_at', 'updated_at', 'id'];
        $allowed = array_merge(Arr::pluck($collection->fields, 'name'), $systemSorts);

        if (empty($sortParam)) {
            return $this->orderByDesc('created_at');
        }

        foreach (explode(',', $sortParam) as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');

            if (in_array($column, $allowed)) {
                $this->orderBy($column, $direction);
            }
        }

        return $this;
    }

    public function applyRule(string $action): static
    {
        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $rule = $collection->api_rules[$action] ?? null;

        if ($rule === null) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        $allowedFields = Arr::pluck($collection->fields, 'name');
        $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)->run($rule));

        return $this;
    }

    public function applyFilter(?string $filter): static
    {
        if (! $filter) {
            return $this;
        }

        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $this->assertRelationFilterNotUsed($filter, $collection->fields ?? []);

        $allowedFields = Arr::pluck($collection->fields ?? [], 'name');

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)->run($filter));
    }

    private function assertRelationFilterNotUsed(string $filter, array $fields): void
    {
        if (preg_match('/\b[a-zA-Z_]+\.[a-zA-Z_]+\b/', $filter) === 1) {
            throw new UnsupportedQueryFeatureException('Filtering nested relation fields is not implemented.');
        }

        $relationFieldNames = collect($fields)
            ->filter(fn (Field|array $field): bool => ($field['type'] ?? null) === CollectionFieldType::Relation->value)
            ->pluck('name')
            ->filter(fn (mixed $fieldName): bool => is_string($fieldName) && $fieldName !== '')
            ->all();

        foreach ($relationFieldNames as $fieldName) {
            $pattern = '/(^|\s|\()'.preg_quote($fieldName, '/').'\s*(=|!=|>|<|>=|<=|in\b|not\s+in\b|like\b|not\s+like\b|is\s+null\b|is\s+not\s+null\b)/i';

            if (preg_match($pattern, $filter) === 1) {
                throw new UnsupportedQueryFeatureException('Filtering relation fields is not implemented.');
            }
        }
    }
}
