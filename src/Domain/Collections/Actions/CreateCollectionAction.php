<?php

namespace Veloquent\Core\Domain\Collections\Actions;

use Illuminate\Support\Arr;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Veloquent\Core\Domain\Collections\Validators\ApiRulesValidator;
use Veloquent\Core\Domain\Collections\Validators\CollectionValidator;
use Veloquent\Core\Domain\Collections\Validators\AuthOptionsValidator;
use Veloquent\Core\Domain\Collections\Validators\CollectionFieldValidator;
use Veloquent\Core\Domain\SchemaManagement\Services\CollectionSyncService;

class CreateCollectionAction
{
    public function __construct(
        private readonly CollectionFieldValidator $collectionFieldValidator,
        private readonly CollectionValidator $collectionValidator,
        private readonly ApiRulesValidator $apiRulesValidator,
        private readonly AuthOptionsValidator $authOptionsValidator,
        private readonly CollectionSyncService $syncService
    ) {}

    public function execute(array $data, bool $skipValidation = false): Collection
    {
        $collectionType = $data['type'] ?? null;

        if ($collectionType instanceof CollectionType) {
            $collectionType = $collectionType->value;
        }

        $isAuthCollection = $collectionType === CollectionType::Auth->value;
        $mergedFields = SchemaChange::mergeWithSystemFields($data['fields'], $isAuthCollection);
        $indexes = $data['indexes'] ?? [];

        if (!$skipValidation) {
            $this->collectionFieldValidator->validateForCreate(
                $data['fields'] ?? [],
                $indexes,
                $isAuthCollection,
            );

            $this->collectionValidator->validateCreate(
                $data['fields'] ?? [],
                $isAuthCollection
            )->throwIfFailed();
        }

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

        return $this->syncService->create([
            'is_system' => $data['is_system'] ?? false,
            ...$data,
            'fields' => $mergedFields,
            'indexes' => $indexes,
        ]);
    }
}
