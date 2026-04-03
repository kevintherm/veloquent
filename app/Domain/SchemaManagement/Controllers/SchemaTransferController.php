<?php

namespace App\Domain\SchemaManagement\Controllers;

use App\Domain\SchemaManagement\Services\SchemaTransferService;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SchemaTransferController extends ApiController
{
    public function __construct(
        private readonly SchemaTransferService $transferService,
    ) {}

    public function options(): JsonResponse
    {
        return $this->successResponse($this->transferService->options());
    }

    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collections' => ['nullable', 'array'],
            'collections.*' => ['string'],
            'system_tables' => ['nullable', 'array'],
            'system_tables.*' => ['string'],
            'include_records' => ['nullable', 'boolean'],
        ]);

        try {
            $payload = $this->transferService->export(
                $validated['collections'] ?? [],
                $validated['system_tables'] ?? [],
                (bool) ($validated['include_records'] ?? true),
            );

            return $this->successResponse($payload);
        } catch (Throwable $throwable) {
            return $this->errorResponse($throwable->getMessage(), 422);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'array'],
            'conflict' => ['nullable', 'string', 'in:skip,overwrite'],
        ]);

        try {
            $result = $this->transferService->import(
                $validated['payload'],
                (string) ($validated['conflict'] ?? 'skip')
            );

            return $this->successResponse($result, 'Import completed successfully.');
        } catch (Throwable $throwable) {
            return $this->errorResponse($throwable->getMessage(), 422);
        }
    }
}
