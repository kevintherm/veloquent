<?php

namespace App\Domain\Records\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Actions\CreateRecordAction;
use App\Domain\Records\Actions\DeleteRecordAction;
use App\Domain\Records\Actions\GetRecordAction;
use App\Domain\Records\Actions\GetRecordsAction;
use App\Domain\Records\Actions\UpdateRecordAction;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordController extends ApiController
{
    public function __construct(
        private GetRecordsAction $getRecordsAction,
        private GetRecordAction $getRecordAction,
        private CreateRecordAction $createRecordAction,
        private UpdateRecordAction $updateRecordAction,
        private DeleteRecordAction $deleteRecordAction
    ) {}

    public function index(Request $request, Collection $collection): JsonResponse
    {
        // Skip auth type collections
        if ($collection->name === 'auth') {
            return $this->errorResponse('Auth collection operations are not allowed', 403);
        }

        $filters = $request->input('filters', '');
        $perPage = $request->input('per_page', 15);

        $records = $this->getRecordsAction->execute($collection, $filters, $perPage);

        return $this->successResponse($records);
    }

    public function store(Request $request, Collection $collection): JsonResponse
    {
        // Skip auth type collections
        if ($collection->name === 'auth') {
            return $this->errorResponse('Auth collection operations are not allowed', 403);
        }

        $data = $request->all();
        $record = $this->createRecordAction->execute($collection, $data);

        return $this->successResponse($record, 'Record created successfully', 201);
    }

    public function show(Collection $collection, string|int $record): JsonResponse
    {
        // Skip auth type collections
        if ($collection->name === 'auth') {
            return $this->errorResponse('Auth collection operations are not allowed', 403);
        }

        $recordData = $this->getRecordAction->execute($collection, $record);

        if (! $recordData) {
            return $this->errorResponse('Record not found', 404);
        }

        return $this->successResponse($recordData);
    }

    public function update(Request $request, Collection $collection, string|int $record): JsonResponse
    {
        // Skip auth type collections
        if ($collection->name === 'auth') {
            return $this->errorResponse('Auth collection operations are not allowed', 403);
        }

        $data = $request->all();
        $recordData = $this->updateRecordAction->execute($collection, $record, $data);

        if (! $recordData) {
            return $this->errorResponse('Record not found', 404);
        }

        return $this->successResponse($recordData, 'Record updated successfully');
    }

    public function destroy(Collection $collection, string|int $record): JsonResponse
    {
        // Skip auth type collections
        if ($collection->name === 'auth') {
            return $this->errorResponse('Auth collection operations are not allowed', 403);
        }

        $deleted = $this->deleteRecordAction->execute($collection, $record);

        if (! $deleted) {
            return $this->errorResponse('Record not found', 404);
        }

        return $this->successResponse([], 'Record deleted successfully');
    }
}
