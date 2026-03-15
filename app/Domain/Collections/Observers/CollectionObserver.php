<?php

namespace App\Domain\Collections\Observers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use App\Domain\SchemaManagement\Services\SchemaDDLService;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Illuminate\Support\Facades\DB;

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

        $tableName = $collection->getPhysicalTableName();

        try {
            $fieldsForDDL = SchemaChangePlan::stripForDDL($collection->fields ?? []);
            $this->ddlService->createTable($tableName, $fieldsForDDL);
            $collection->schema_updated_at = now();
        } catch (\Throwable $e) {
            if (! $this->isMySQL()) {
                SchemaJob::where('table_name', $tableName)->delete();
            }

            throw $e;
        }
    }

    public function created(Collection $collection): void
    {
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

        $this->startJob($collection, SchemaOperation::Update);

        $tableName = $collection->getPhysicalTableName();

        try {
            if ($collection->isDirty('name')) {
                $oldTableName = Collection::formatTableName($collection->getOriginal('name'), $collection->is_system);
                $this->ddlService->renameTable($oldTableName, $collection->getPhysicalTableName());
                $collection->schema_updated_at = now();
            }

            if ($collection->isDirty('fields')) {
                $originalFields = $this->extractFields($collection->getOriginal('fields'));
                $newFields = $this->extractFields($collection->fields ?? []);

                $isAuthCollection = $collection->type === CollectionType::Auth;
                $originalFieldsForDDL = SchemaChangePlan::stripForDDL($originalFields);
                $newFieldsForDDL = SchemaChangePlan::stripForDDL($newFields);

                $this->ddlService->updateTable(
                    $tableName,
                    $originalFieldsForDDL,
                    $newFieldsForDDL,
                    $isAuthCollection
                );
            }

            $collection->schema_updated_at = now();
        } catch (\Throwable $e) {
            if (! $this->isMySQL()) {
                SchemaJob::where('table_name', $tableName)->delete();
            }

            throw $e;
        }
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

    private function isMySQL(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    private function extractFields(mixed $fields): array
    {
        if (is_array($fields)) {
            return $fields;
        }

        if (is_string($fields) && $fields !== '') {
            $decoded = json_decode($fields, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
