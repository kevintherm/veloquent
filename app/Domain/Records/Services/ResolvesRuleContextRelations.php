<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;

class ResolvesRuleContextRelations
{
    /**
     * Hydrate the context with related records discovered in the rule.
     */
    public function resolve(Collection $collection, array &$context, string $rule): void
    {
        $paths = $this->extractPaths($rule);

        foreach ($paths as $path) {
            $this->hydratePath($collection, $context, $path);
        }
    }

    /**
     * Extract potential field paths from the rule string.
     *
     * @return array<string>
     */
    private function extractPaths(string $rule): array
    {
        preg_match_all('/(?<!@)\b([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+)\b/', $rule, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * Hydrate a specific path in the context.
     */
    private function hydratePath(Collection $collection, array &$context, string $path): void
    {
        $parts = explode('.', $path);
        $currentCollection = $collection;
        $currentContext = &$context;

        foreach ($parts as $part) {
            $field = collect($currentCollection->fields ?? [])
                ->first(fn ($f) => ($f['name'] ?? '') === $part);

            if (! $field || ($field['type'] ?? '') !== CollectionFieldType::Relation->value) {
                return;
            }

            $relationId = $currentContext[$part] ?? null;

            if (is_array($relationId)) {
                $targetCollectionId = $field['target_collection_id'] ?? null;
                if (! $targetCollectionId) {
                    return;
                }

                $targetCollection = Collection::query()->find($targetCollectionId);
                if (! $targetCollection) {
                    return;
                }

                $currentCollection = $targetCollection;
                $currentContext = &$currentContext[$part];

                continue;
            }

            if (is_string($relationId) && ! empty($relationId)) {
                $targetCollectionId = $field['target_collection_id'] ?? null;
                if (! $targetCollectionId) {
                    return;
                }

                $targetCollection = Collection::query()->find($targetCollectionId);
                if (! $targetCollection) {
                    return;
                }

                $relatedRecord = Record::of($targetCollection)->newQuery()->find($relationId);

                if ($relatedRecord) {
                    $currentContext[$part] = $relatedRecord->toArray();

                    $currentCollection = $targetCollection;
                    $currentContext = &$currentContext[$part];
                } else {
                    return;
                }
            } else {
                return;
            }
        }
    }
}
