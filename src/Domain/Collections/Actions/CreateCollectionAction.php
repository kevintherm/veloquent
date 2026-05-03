<?php

namespace Veloquent\Core\Domain\Collections\Actions;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Validators\ApiRulesValidator;
use Veloquent\Core\Domain\Collections\Validators\AuthOptionsValidator;
use Veloquent\Core\Domain\Collections\Validators\CollectionFieldValidator;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;

class CreateCollectionAction
{
    public function __construct(
        private readonly CollectionFieldValidator $collectionFieldValidator,
        private readonly ApiRulesValidator $apiRulesValidator,
        private readonly AuthOptionsValidator $authOptionsValidator
    ) {}

    public function execute(array $data): Collection
    {
        $collectionType = $data['type'] ?? null;

        if ($collectionType instanceof CollectionType) {
            $collectionType = $collectionType->value;
        }

        $isAuthCollection = $collectionType === CollectionType::Auth->value;
        $mergedFields = SchemaChangePlan::mergeWithSystemFields($data['fields'], $isAuthCollection);
        $indexes = $data['indexes'] ?? [];

        $this->collectionFieldValidator->validateForCreate(
            $data['fields'] ?? [],
            $indexes,
            $isAuthCollection,
        );

        if (isset($data['api_rules'])) {
            $data['api_rules'] = $this->apiRulesValidator->validate(
                $data['api_rules'],
                $mergedFields,
                $isAuthCollection
            );
        }

        if (isset($data['options']) || $isAuthCollection) {
            $data['options'] = $this->authOptionsValidator->validate(
                $data['options'] ?? [],
                Arr::pluck($mergedFields, 'name'),
                $isAuthCollection
            );
        }

        return Collection::create([
            'is_system' => $data['is_system'] ?? false,
            ...$data,
            'table_name' => SchemaChangePlan::generateTableName($data['name'], $data['is_system'] ?? false),
            'fields' => $mergedFields,
            'indexes' => $indexes,
        ]);
    }
}
