<?php

namespace App\Http\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Models\OAuthProvider;
use App\Domain\OAuth\Validators\ValidOAuthDriver;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OAuthProviderController extends ApiController
{
    public function index(Collection $collection): JsonResponse
    {
        $providers = OAuthProvider::query()
            ->where('collection_id', $collection->id)
            ->get();

        return $this->successResponse($providers);
    }

    public function store(Request $request, Collection $collection): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(ValidOAuthDriver::VALID_DRIVERS), Rule::unique('oauth_providers')->where('collection_id', $collection->id)],
            'enabled' => 'boolean',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri' => 'nullable|string|url',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
        ]);

        $provider = OAuthProvider::create([
            ...$validated,
            'collection_id' => $collection->id,
        ]);

        $this->clearProviderCache($collection->id, $validated['provider']);

        return $this->successResponse($provider, 'OAuth provider created.', Response::HTTP_CREATED);
    }

    public function update(Request $request, Collection $collection, OAuthProvider $oauthProvider): JsonResponse
    {
        if ($oauthProvider->collection_id !== $collection->id) {
            return $this->errorResponse('OAuth provider does not belong to this collection.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'enabled' => 'boolean',
            'client_id' => 'sometimes|required|string',
            'client_secret' => 'sometimes|required|string',
            'redirect_uri' => 'nullable|string|url',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
        ]);

        $oauthProvider->update($validated);

        $this->clearProviderCache($collection->id, $oauthProvider->provider);

        return $this->successResponse($oauthProvider, 'OAuth provider updated.');
    }

    public function destroy(Collection $collection, OAuthProvider $oauthProvider): JsonResponse
    {
        if ($oauthProvider->collection_id !== $collection->id) {
            return $this->errorResponse('OAuth provider does not belong to this collection.', Response::HTTP_NOT_FOUND);
        }

        $this->clearProviderCache($collection->id, $oauthProvider->provider);

        $oauthProvider->delete();

        return $this->successResponse([], 'OAuth provider deleted.');
    }

    private function clearProviderCache(string $collectionId, string $provider): void
    {
        Cache::forget("oauth_config:{$collectionId}:{$provider}");
    }
}
