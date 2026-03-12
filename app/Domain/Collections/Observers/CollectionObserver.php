<?php

namespace App\Domain\Collections\Observers;

use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use App\Domain\SchemaManagement\Services\SchemaDDLService;
use App\Infrastructure\Exceptions\InvalidArgumentException;

readonly class CollectionObserver
{
    public function __construct(
        private SchemaDDLService $ddlService
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function creating(Collection $collection): void
    {
        $this->validateApiRules($collection);

        $this->startJob($collection, SchemaOperation::Create);

        $userFields = SchemaChangePlan::cleanFields($collection->fields);
        $this->ddlService->createTable($collection->getPhysicalTableName(), $userFields);
        $collection->schema_updated_at = now();
    }

    public function created(Collection $collection): void
    {
        $collection->fields = SchemaChangePlan::mergeWithSystemFields($collection->fields ?? []);
        $this->endJob($collection);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updating(Collection $collection): void
    {
        $this->validateApiRules($collection);

        if ($collection->isDirty('type')) {
            throw new InvalidArgumentException('Collection type cannot be changed');
        }

        if ($collection->isDirty('fields')) {
            $collection->fields = SchemaChangePlan::mergeWithSystemFields($collection->fields ?? []);
        }

        $this->startJob($collection, SchemaOperation::Update);

        if ($collection->isDirty('name')) {
            $oldTableName = Collection::formatTableName($collection->getOriginal('name'), $collection->is_system);
            $this->ddlService->renameTable($oldTableName, $collection->getPhysicalTableName());
            $collection->schema_updated_at = now();
        }

        if ($collection->isDirty('fields')) {
            $originalFields = $collection->getOriginal('fields') ?? [];
            $newFields = $collection->fields ?? [];

            $originalFieldsCleaned = SchemaChangePlan::cleanFields($originalFields);
            $newFieldsCleaned = SchemaChangePlan::cleanFields($newFields);

            $this->ddlService->updateTable(
                $collection->getPhysicalTableName(),
                $originalFieldsCleaned,
                $newFieldsCleaned
            );
        }

        $collection->schema_updated_at = now();
    }

    public function updated(Collection $collection): void
    {
        $this->endJob($collection);
    }

    public function deleting(Collection $collection): void
    {
        $this->startJob($collection, SchemaOperation::Drop);

        $this->ddlService->deleteTable($collection->getPhysicalTableName());
    }

    public function deleted(Collection $collection): void
    {
        $this->endJob($collection);
    }

    private function startJob(Collection $collection, SchemaOperation $op): void
    {
        SchemaJob::create([
            'collection_id' => $collection->id,
            'table_name' => $collection->getPhysicalTableName(),
            'operation' => $op,
            'started_at' => now(),
        ]);
    }

    private function endJob(Collection $collection): void
    {
        SchemaJob::where('table_name', $collection->getPhysicalTableName())->delete();
    }

    private function validateApiRules(Collection $collection): void
    {
        $collection->api_rules = array_merge([
            'list' => null,
            'view' => null,
            'create' => null,
            'update' => null,
            'delete' => null,
        ], $collection->api_rules ?? []);

        $validKeys = ['list', 'view', 'create', 'update', 'delete'];
        $invalidKeys = array_diff(array_keys($collection->api_rules ?? []), $validKeys);

        if (! empty($invalidKeys)) {
            throw new \InvalidArgumentException('Invalid api rules keys: '.implode(', ', $invalidKeys));
        }
    }
}
