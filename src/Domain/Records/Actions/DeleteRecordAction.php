<?php

namespace Veloquent\Core\Domain\Records\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Veloquent\Core\Domain\Hooks\HookRunner;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\ValueObjects\Field;
use Veloquent\Core\Domain\Records\Services\PivotSyncService;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;

class DeleteRecordAction
{
    public function __construct(
        private readonly HookRunner $hookRunner,
        private readonly PivotSyncService $pivotSyncService,
        private readonly RelationIntegrityService $relationIntegrityService,
    ) {}
    public function execute(Collection $collection, string $recordId): void
    {
        Gate::authorize('delete-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection)->newQuery();

        if (! $bypassApiRules) {
            $query->applyRule('delete');
        }

        $record = $query->findOrFail($recordId);

        DB::transaction(function () use ($collection, $record, $authenticatedUser) {
            $this->hookRunner->run(new HookPayload(
                event: 'record.deleting',
                collection: $collection,
                record: $record,
                request: request(),
                actor: $authenticatedUser instanceof Record ? $authenticatedUser : null,
            ));

            $record->delete();

            $relationManyFields = collect($collection->fields ?? [])
                ->filter(fn (Field|array $f) => ($f['type'] ?? '') === CollectionFieldType::RelationMany->value)
                ->all();

            foreach ($relationManyFields as $field) {
                $targetCollection = Collection::find($field['target_collection_id'] ?? '');
                if ($targetCollection) {
                    $pivotTable = PivotTableName::for($collection->getPhysicalTableName(), $targetCollection->getPhysicalTableName(), $field['name']);
                    $this->pivotSyncService->detachAll(
                        $pivotTable,
                        'source_id',
                        (string) $record->getKey()
                    );
                }
            }

            // Clean up pivot entries where the record is the TARGET
            $referencingFields = $this->relationIntegrityService->findReferencingFields($collection->id);
            foreach ($referencingFields as $ref) {
                $refCollection = $ref['collection'];
                $refField = $ref['field'];

                if (($refField['type'] ?? '') === CollectionFieldType::RelationMany->value) {
                    $pivotTable = PivotTableName::for(
                        $refCollection->getPhysicalTableName(),
                        $collection->getPhysicalTableName(),
                        $refField['name']
                    );
                    $this->pivotSyncService->detachAll(
                        $pivotTable,
                        'target_id',
                        (string) $record->getKey()
                    );
                }
            }
        });

        $this->hookRunner->run(new HookPayload(
            event: 'record.deleted',
            collection: $collection,
            record: $record,
            request: request(),
            actor: $authenticatedUser instanceof Record ? $authenticatedUser : null,
        ));
    }
}
