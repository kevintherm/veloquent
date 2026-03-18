<?php

namespace App\Http\Controllers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingController extends ApiController
{
    public function initialized(Request $request): JsonResponse
    {
        $collection = Collection::where('type', CollectionType::Auth)->where('name', 'superusers')->first();
        return $this->successResponse($collection->exists());
    }

    public function createSuperuser(Request $request): JsonResponse
    {
        $collection = Collection::where('type', CollectionType::Auth)->where('name', 'superusers')->first();

        if (! $collection) {
            return $this->errorResponse('Superusers collection not found', Response::HTTP_NOT_FOUND);
        }

        $superuser = Record::of($collection);

        if ($superuser->exists()) {
            return $this->errorResponse('Superuser already exists', Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:superusers'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $superuser = $superuser->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return $this->successResponse([
            'id' => $superuser->id,
            'name' => $superuser->name,
            'email' => $superuser->email,
            'created_at' => $superuser->created_at,
        ], 'Superuser created successfully', Response::HTTP_CREATED);
    }
}
