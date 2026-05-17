<?php

namespace Veloquent\Core\Domain\SchemaManagement\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Models\SchemaJob;
use Veloquent\Core\Infrastructure\Http\Controllers\ApiController;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaRecoveryService;

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
