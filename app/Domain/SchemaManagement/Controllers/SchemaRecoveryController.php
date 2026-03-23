<?php

namespace App\Domain\SchemaManagement\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Domain\SchemaManagement\Services\SchemaRecoveryService;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SchemaRecoveryController extends ApiController
{
    public function __construct(
        private readonly SchemaRecoveryService $recoveryService
    ) {}

    public function index(): JsonResponse
    {
        $jobs = SchemaJob::with('collection')->get();

        return $this->successResponse($jobs);
    }

    public function recover(Collection $collection): JsonResponse
    {
        Gate::authorize('update-collections', [$collection->toArray()]);

        $this->recoveryService->recover($collection);

        return $this->successResponse([], 'Collection schema recovered successfully.');
    }
}
