<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Records\Support\PivotTableName;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaDDLService;
use Closure;

class DropPivotTables
{
    public function __construct(private readonly SchemaDDLService $ddlService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        $sourceName = $context->collection->name;

        foreach ($context->collection->fields ?? [] as $field) {
            if (($field['type'] ?? '') === CollectionFieldType::RelationMany->value) {
                $targetCollection = \Veloquent\Core\Domain\Collections\Models\Collection::findByIdCached($field['target_collection_id'] ?? '');
                $targetName = $targetCollection?->name ?? 'unknown';

                $this->ddlService->deleteTable(
                    PivotTableName::for($sourceName, $targetName, $field['name'])
                );
            }
        }

        return $next($context);
    }
}
