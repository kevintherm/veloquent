<?php

namespace App\Infrastructure\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    protected function successResponse(mixed $data, string $message = 'Success', int $code = Response::HTTP_OK, ?Cookie $cookie = null): JsonResponse
    {
        $response = [
            'message' => $message,
        ];

        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;
            $response['data'] = $data->collection;
            $response['meta'] = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ];
        } elseif ($data instanceof LengthAwarePaginator) {
            $response['data'] = $data->items();
            $response['meta'] = [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more_pages' => $data->hasMorePages(),
            ];
        } else {
            $response['data'] = $data;
        }

        $resp = response()->json($response, $code);

        if ($cookie instanceof Cookie) {
            $resp->cookie($cookie);
        }

        return $resp;
    }

    protected function errorResponse(string $message, int $code, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function forbiddenResponse(string $message, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], Response::HTTP_FORBIDDEN);
    }
}
