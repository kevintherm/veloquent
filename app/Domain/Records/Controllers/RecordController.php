<?php

namespace App\Domain\Records\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Actions\CreateRecordAction;
use App\Domain\Records\Actions\DeleteRecordAction;
use App\Domain\Records\Actions\GetRecordsAction;
use App\Domain\Records\Actions\ShowRecordAction;
use App\Domain\Records\Actions\UpdateRecordAction;
use App\Domain\Records\Requests\StoreRecordRequest;
use App\Domain\Records\Requests\UpdateRecordRequest;
use App\Domain\Records\Resources\RecordResource;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordController extends ApiController
{
    public function __construct(
        private GetRecordsAction $getRecordsAction,
        private CreateRecordAction $createRecordAction,
        private ShowRecordAction $showRecordAction,
        private UpdateRecordAction $updateRecordAction,
        private DeleteRecordAction $deleteRecordAction,
    ) {
    }

    public function index(Request $request, Collection $collection): JsonResponse
    {
        $records = $this->getRecordsAction->execute(
            $collection,
            $request->input('sort') ?? '',
            $request->input('filter') ?? '',
            $request->input('per_page', 15)
        );

        $records = RecordResource::collection($records);

        return $this->successResponse($records);
    }

    public function store(StoreRecordRequest $request, Collection $collection): JsonResponse
    {
        $record = $this->createRecordAction->execute(
            $collection,
            $request->getRecordData()
        );

        $record = new RecordResource($record);

        return $this->successResponse($record, 'Record created successfully', 201);
    }

    public function show(Collection $collection, string $recordId): JsonResponse
    {
        $record = $this->showRecordAction->execute($collection, $recordId);

        $record = new RecordResource($record);

        return $this->successResponse($record);
    }

    public function update(UpdateRecordRequest $request, Collection $collection, string $recordId): JsonResponse
    {
        $updatedRecord = $this->updateRecordAction->execute(
            $collection,
            $recordId,
            $request->getRecordData(),
        );

        $record = new RecordResource($updatedRecord);

        return $this->successResponse($record, 'Record updated successfully');
    }

    public function destroy(Collection $collection, string $recordId): JsonResponse
    {
        $this->deleteRecordAction->execute($collection, $recordId);

        return $this->successResponse([], 'Record deleted successfully.');
    }
}
