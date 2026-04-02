<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\QueryCompiler\Exceptions\UnsupportedQueryFeatureException;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Resources\RecordResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RecordExpansionService
{
    public function expandMany(Collection $sourceCollection, array $records, ?string $expand): void
    {
        $relationFields = $this->parse($sourceCollection, $expand);

        if ($relationFields === []) {
            return;
        }

        $relationIdsByField = [];
        $targetCollectionIds = [];

        foreach ($relationFields as $field) {
            if ($id = ($field['target_collection_id'] ?? null)) {
                $targetCollectionIds[] = $id;
            }
        }

        $targetCollectionsById = Collection::query()
            ->whereIn('id', array_unique($targetCollectionIds))
            ->get()
            ->keyBy('id');

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
        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        foreach ($relationFields as $fieldName => $field) {
            $targetCollection = $targetCollectionsById->get($field['target_collection_id'] ?? '');
            $relationIds = array_values(array_unique($relationIdsByField[$fieldName] ?? []));

            if ($targetCollection === null) {
                Log::warning('EXPANSION_TARGET_COLLECTION_MISSING', [
                    'source_collection' => $sourceCollection->id,
                    'field' => $fieldName,
                    'target_collection_id' => $field['target_collection_id'] ?? null,
                ]);
                $resolvedByField[$fieldName] = [];

                continue;
            }

            if ($relationIds === []) {
                $resolvedByField[$fieldName] = [];

                continue;
            }

            $query = Record::of($targetCollection)->newQuery();

            if (! $bypassApiRules) {
                $query->applyRule('view');
            }

            $resolvedByField[$fieldName] = $query
                ->whereIn('id', $relationIds)
                ->get()
                ->mapWithKeys(function (Record $targetRecord): array {
                    $resource = new RecordResource($targetRecord);

                    return [(string) $targetRecord->getAttribute('id') => $resource->resolve()];
                })
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

    /**
     * @return array<string, array<string, mixed>|Field>
     */
    public function parse(Collection $sourceCollection, ?string $expand): array
    {
        $expand = trim((string) ($expand ?? ''));

        if ($expand === '') {
            return [];
        }

        /** @var array<string, array<string, mixed>|Field> $fieldsByName */
        $fieldsByName = collect($sourceCollection->fields ?? [])
            ->keyBy(fn ($field): string => (string) $field['name'])
            ->all();

        $expandFields = collect(explode(',', $expand))
            ->map(fn (string $fieldName): string => trim($fieldName))
            ->filter(fn (string $fieldName): bool => $fieldName !== '')
            ->unique()
            ->values();

        $maxExpansions = config('velo.records_expand_max', 10);

        if ($expandFields->count() > $maxExpansions) {
            throw new UnsupportedQueryFeatureException("A maximum of {$maxExpansions} relation expansions are allowed per request.");
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

    /**
     * @param  array<string, mixed>  $field
     */
    private function resolveTargetCollection(array $field): ?Collection
    {
        // This method is now shadowed by batch resolution in expandMany but kept for internal parse-time validation if needed.
        $targetCollectionId = $field['target_collection_id'] ?? null;

        if (! is_string($targetCollectionId) || $targetCollectionId === '') {
            return null;
        }

        return Collection::query()->find($targetCollectionId);
    }
}
