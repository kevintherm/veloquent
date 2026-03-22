<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\QueryCompiler\Exceptions\UnsupportedQueryFeatureException;
use App\Domain\Records\Models\Record;

class RecordExpansionService
{
    /**
     * @TODO: Circular relation detection for nested expand queries is not yet implemented.
     */
    public function expandMany(Collection $sourceCollection, array $records, ?string $expand): void
    {
        $relationFields = $this->parse($sourceCollection, $expand);

        if ($relationFields === []) {
            return;
        }

        $relationIdsByField = [];

        foreach ($records as $record) {
            if (! $record instanceof Record) {
                continue;
            }

            foreach ($relationFields as $fieldName => $field) {
                $relationId = $record->getAttribute($fieldName);

                if (! is_string($relationId) || $relationId === '') {
                    continue;
                }

                $relationIdsByField[$fieldName] ??= [];
                $relationIdsByField[$fieldName][] = $relationId;
            }
        }

        $resolvedByField = [];

        foreach ($relationFields as $fieldName => $field) {
            $targetCollection = $this->resolveTargetCollection($field);
            $relationIds = array_values(array_unique($relationIdsByField[$fieldName] ?? []));

            if ($targetCollection === null || $relationIds === []) {
                $resolvedByField[$fieldName] = [];

                continue;
            }

            $resolvedByField[$fieldName] = Record::of($targetCollection)
                ->newQuery()
                ->whereIn('id', $relationIds)
                ->get()
                ->mapWithKeys(fn (Record $targetRecord): array => [
                    (string) $targetRecord->getAttribute('id') => $targetRecord->toArray(),
                ])
                ->all();
        }

        foreach ($records as $record) {
            if (! $record instanceof Record) {
                continue;
            }

            $expanded = [];

            foreach ($relationFields as $fieldName => $field) {
                $relationId = $record->getAttribute($fieldName);
                $resolved = $resolvedByField[$fieldName] ?? [];

                $expanded[$fieldName] = is_string($relationId) ? ($resolved[$relationId] ?? null) : null;
            }

            $record->expandedRelations = $expanded;
        }
    }

    public function parse(Collection $sourceCollection, ?string $expand): array
    {
        $expand = trim((string) ($expand ?? ''));

        if ($expand === '') {
            return [];
        }

        $fieldsByName = collect($sourceCollection->fields ?? [])
            ->keyBy(fn (Field|array $field): string => (string) $field['name'])
            ->all();

        $expandFields = collect(explode(',', $expand))
            ->map(fn (string $fieldName): string => trim($fieldName))
            ->filter(fn (string $fieldName): bool => $fieldName !== '')
            ->unique()
            ->values();

        if ($expandFields->count() > 10) {
            throw new UnsupportedQueryFeatureException('A maximum of 10 relation expansions are allowed per request.');
        }

        $relationFields = [];

        foreach ($expandFields as $fieldName) {
            if (str_contains($fieldName, '.')) {
                throw new UnsupportedQueryFeatureException('Nested relation expansion is not implemented.');
            }

            if (! isset($fieldsByName[$fieldName])) {
                throw new InvalidRuleExpressionException("Unknown expand field: {$fieldName}");
            }

            $field = $fieldsByName[$fieldName];

            if (($field['type'] ?? null) !== CollectionFieldType::Relation->value) {
                throw new InvalidRuleExpressionException("Field '{$fieldName}' is not a relation field.");
            }

            $relationFields[$fieldName] = $field;
        }

        return $relationFields;
    }

    private function resolveTargetCollection(Field|array $field): ?Collection
    {
        $targetCollectionId = $field['target_collection_id'] ?? null;

        if (! is_string($targetCollectionId) || $targetCollectionId === '') {
            return null;
        }

        return Collection::query()->find($targetCollectionId);
    }
}
