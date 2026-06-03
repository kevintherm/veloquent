<?php

namespace Veloquent\Core\Domain\Collections\Observers;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Support\TableName;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use Veloquent\Core\Support\Exceptions\InvalidArgumentException;
use Veloquent\Core\Domain\SchemaManagement\Services\CollectionSyncService;

readonly class CollectionObserver
{
    /**
     * @throws InvalidArgumentException
     */
    public function creating(Collection $collection): void
    {
        $this->validateApiRules($collection);
        $this->ensureTableNameIsSet($collection);
    }

    public function created(Collection $collection): void
    {
        if (! CollectionSyncService::isSyncing()) {
            app(CollectionSyncService::class)->sync($collection, SchemaOperation::Create);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updating(Collection $collection): void
    {
        $this->validateApiRules($collection);
        $this->ensureTypeIsNotChanged($collection);
    }

    public function updated(Collection $collection): void
    {
        if (! CollectionSyncService::isSyncing()) {
            app(CollectionSyncService::class)->sync($collection, SchemaOperation::Update);
        }
    }

    public function deleted(Collection $collection): void
    {
        if (! CollectionSyncService::isSyncing()) {
            app(CollectionSyncService::class)->drop($collection);
        }
    }

    private function ensureTableNameIsSet(Collection $collection): void
    {
        if (empty($collection->table_name)) {
            $collection->table_name = TableName::for(
                $collection->name,
                $collection->is_system ?? false
            );
        }
    }

    private function ensureTypeIsNotChanged(Collection $collection): void
    {
        if ($collection->isDirty('type')) {
            $original = $collection->getOriginal('type');
            if ($original === null) {
                return;
            }

            $current = $collection->type;
            $originalValue = $original instanceof \BackedEnum ? $original->value : $original;
            $currentValue = $current instanceof \BackedEnum ? $current->value : $current;

            if ($originalValue !== $currentValue) {
                throw new InvalidArgumentException('Collection type cannot be changed');
            }
        }
    }

    private function validateApiRules(Collection $collection): void
    {
        $defaults = [
            'list' => null,
            'view' => null,
            'create' => null,
            'update' => null,
            'delete' => null,
            'manage' => null,
        ];

        $type = $collection->type;
        $typeValue = $type instanceof \BackedEnum ? $type->value : $type;

        if ($typeValue === 'agents') {
            $defaults['chat'] = null;
        }

        $validKeys = array_keys($defaults);

        $collection->api_rules = array_merge($defaults, $collection->api_rules ?? []);
        $invalidKeys = array_diff(array_keys($collection->api_rules ?? []), $validKeys);

        if (! empty($invalidKeys)) {
            throw new InvalidArgumentException('Invalid api rules keys: '.implode(', ', $invalidKeys));
        }
    }
}
