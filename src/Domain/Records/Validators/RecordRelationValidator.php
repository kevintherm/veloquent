<?php

namespace Veloquent\Core\Domain\Records\Validators;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\ValueObjects\Field;
use Veloquent\Core\Domain\Records\Models\Record;

class RecordRelationValidator
{
    /**
     * Validate the relationship IDs for a specific collection and field.
     *
     * @param  Collection  $collection The source collection
     * @param  array|Field  $field The relation field configuration
     * @param  array  $ids The target record IDs
     * @return string|null Error message if validation fails, or null if it passes
     */
    public function validate(Collection $collection, array|Field $field, array $ids): ?string
    {
        $fieldName = $field instanceof Field ? $field->name : ($field['name'] ?? null);

        if ($collection->type === CollectionType::Agents && $fieldName === 'watchers') {
            return $this->validateAgentsWatchers($collection, $field, $ids);
        }

        return null;
    }

    /**
     * Validate watchers relation for Agents collection.
     */
    private function validateAgentsWatchers(Collection $collection, array|Field $field, array $ids): ?string
    {
        $targetCollectionId = $field instanceof Field ? $field->target_collection_id : ($field['target_collection_id'] ?? null);
        if (! $targetCollectionId) {
            return null;
        }

        $targetCollection = Collection::query()->find($targetCollectionId);
        if (! $targetCollection) {
            return null;
        }

        $watcherCount = Record::of($targetCollection)
            ->newQuery()
            ->whereIn('id', $ids)
            ->where('type', 'watcher')
            ->count();

        if ($watcherCount !== count(array_unique($ids))) {
            return 'Only agents of type watcher can be added as watchers.';
        }

        return null;
    }
}
