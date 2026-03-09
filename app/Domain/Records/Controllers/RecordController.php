<?php

namespace App\Domain\Records\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Actions\CreateRecordAction;
use App\Domain\Records\Actions\DeleteRecordAction;
use App\Domain\Records\Actions\GetRecordAction;
use App\Domain\Records\Actions\GetRecordsAction;
use App\Domain\Records\Actions\UpdateRecordAction;
use App\Domain\Records\Requests\StoreRecordRequest;
use App\Domain\Records\Requests\UpdateRecordRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RecordController extends ApiController
{
    public function __construct(
        private GetRecordsAction $getRecordsAction,
        private GetRecordAction $getRecordAction,
        private CreateRecordAction $createRecordAction,
        private UpdateRecordAction $updateRecordAction,
        private DeleteRecordAction $deleteRecordAction
    ) {}

    public function index(Collection $collection): JsonResponse
    {
        Gate::authorize('list-records', $collection);

        $filters = request()->input('filters', '');
        $perPage = min(request()->input('per_page', 15), 100);

        $records = $this->getRecordsAction->execute($collection, $filters, $perPage);

        return $this->successResponse($records);
    }

    public function store(StoreRecordRequest $request, Collection $collection): JsonResponse
    {
        Gate::authorize('create-records', $collection);

        $record = $this->createRecordAction->execute(
            $collection,
            $request->getRecordData()
        );

        return $this->successResponse($record, 'Record created successfully', 201);
    }

    public function show(Collection $collection, string $record): JsonResponse
    {
        Gate::authorize('view-records', $collection);

        $record = $this->getRecordAction->execute($collection, $record);

        return $this->successResponse($record);
    }

    public function update(UpdateRecordRequest $request, Collection $collection, string $record): JsonResponse
    {
        Gate::authorize('update-records', $collection);

        $updatedRecord = $this->updateRecordAction->execute(
            $collection,
            $record,
            $request->getRecordData()
        );

        return $this->successResponse($updatedRecord, 'Record updated successfully');
    }

    public function destroy(Collection $collection, string $record): JsonResponse
    {
        Gate::authorize('delete-records', $collection);

        $this->deleteRecordAction->execute($collection, $record);

        return $this->successResponse([], 'Record deleted successfully');
    }
}
