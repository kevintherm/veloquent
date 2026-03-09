<?php

namespace App\Http\Controllers;

use App\Infrastructure\Http\Controllers\ApiController;
use App\Models\Superuser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingController extends ApiController
{
    public function createSuperuser(Request $request): JsonResponse
    {
        // Check if superuser already exists
        if (Superuser::exists()) {
            return $this->errorResponse('Superuser already exists', Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:superusers'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $superuser = Superuser::create([
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
