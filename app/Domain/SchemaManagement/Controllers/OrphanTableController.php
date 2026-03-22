<?php

namespace App\Domain\SchemaManagement\Controllers;

use App\Domain\SchemaManagement\Services\OrphanTableService;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrphanTableController extends ApiController
{
    public function __construct(
        private readonly OrphanTableService $orphanService
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('manage-schema');

        $orphans = $this->orphanService->getOrphans();

        return $this->successResponse($orphans);
    }

    public function destroy(string $tableName): JsonResponse
    {
        Gate::authorize('manage-schema');

        $this->orphanService->dropTable($tableName);

        return $this->successResponse([], "Orphan table '{$tableName}' dropped successfully.");
    }

    public function destroyAll(): JsonResponse
    {
        Gate::authorize('manage-schema');

        $this->orphanService->dropAllOrphans();

        return $this->successResponse([], 'All orphan tables dropped successfully.');
    }
}
