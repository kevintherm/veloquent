<?php

namespace Veloquent\Core\Domain\Records\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\PivotSyncService;
use Veloquent\Core\Domain\Records\Support\PivotTableName;
use Veloquent\Core\Infrastructure\Http\Controllers\ApiController;

class RelationSyncController extends ApiController
{
    public function __construct(
        private readonly PivotSyncService $pivotSyncService
    ) {}

    /**
     * Full idempotent sync of a many-to-many relation.
     */
    public function sync(Request $request, Collection $collection, string $recordId, string $fieldName): JsonResponse
    {
        Gate::authorize('update-records', $collection);

        $record = Record::of($collection)->findOrFail($recordId);

        $field = $this->resolveRelationManyField($collection, $fieldName);
        if (! $field) {
            return $this->errorResponse('Invalid many-to-many field.', 400);
        }

        $targetCollection = $this->resolveTargetCollection($field);
        if (! $targetCollection) {
            return $this->errorResponse('Target collection not found.', 404);
        }

        $entries = $this->preparePivotEntries($request);

        $pivotTable = PivotTableName::for($collection->getPhysicalTableName(), $targetCollection->getPhysicalTableName(), $fieldName);
        $this->pivotSyncService->sync(
            $pivotTable,
            'source_id',
            'target_id',
            (string) $record->getKey(),
            $entries
        );

        return $this->successResponse([], 'Relation synced successfully.');
    }

    /**
     * Detach all related records for a many-to-many relation.
     */
    public function detach(Collection $collection, string $recordId, string $fieldName): JsonResponse
    {
        Gate::authorize('update-records', $collection);

        $record = Record::of($collection)->findOrFail($recordId);

        $field = $this->resolveRelationManyField($collection, $fieldName);
        if (! $field) {
            return $this->errorResponse('Invalid many-to-many field.', 400);
        }

        $targetCollection = $this->resolveTargetCollection($field);
        if (! $targetCollection) {
            return $this->errorResponse('Target collection not found.', 404);
        }

        $pivotTable = PivotTableName::for($collection->getPhysicalTableName(), $targetCollection->getPhysicalTableName(), $fieldName);
        $this->pivotSyncService->detachAll(
            $pivotTable,
            'source_id',
            (string) $record->getKey()
        );

        return $this->successResponse([], 'Relation detached successfully.');
    }

    private function resolveRelationManyField(Collection $collection, string $fieldName): ?array
    {
        $field = collect($collection->fields ?? [])->first(fn ($f) => ($f['name'] ?? '') === $fieldName);

        if (! $field || ($field['type'] ?? '') !== CollectionFieldType::RelationMany->value) {
            return null;
        }

        return $field;
    }

    private function resolveTargetCollection(array $field): ?Collection
    {
        $targetId = $field['target_collection_id'] ?? null;
        return $targetId ? Collection::find($targetId) : null;
    }

    private function preparePivotEntries(Request $request): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'string'],
            'pivot' => ['sometimes', 'array'],
        ]);

        $pivotData = $data['pivot'] ?? [];
        $entries = [];

        foreach ($data['ids'] as $id) {
            $entry = ['id' => $id];
            if (isset($pivotData[$id]) && is_array($pivotData[$id])) {
                $entry = array_merge($entry, $pivotData[$id]);
            }
            $entries[] = $entry;
        }

        return $entries;
    }
}
