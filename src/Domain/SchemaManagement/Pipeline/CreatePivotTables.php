<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaDDLService;
use Closure;

class CreatePivotTables
{
    public function __construct(private readonly SchemaDDLService $ddlService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        if (! $context->isCreate()) {
            return $next($context);
        }

        $sourceName = $context->collection->name;

        foreach ($context->newFields as $field) {
            if (($field['type'] ?? '') === CollectionFieldType::RelationMany->value) {
                $targetCollection = \Veloquent\Core\Domain\Collections\Models\Collection::findByIdCached($field['target_collection_id'] ?? '');
                $targetName = $targetCollection?->name ?? 'unknown';

                $this->ddlService->createPivotTable(
                    PivotTableName::for($sourceName, $targetName, $field['name']),
                    'source_id',
                    'target_id'
                );
            }
        }

        return $next($context);
    }
}
