<?php

namespace App\Http\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Services\OAuthService;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthController extends ApiController
{
    public function __construct(
        private OAuthService $oauthService,
    ) {}

    public function redirect(Request $request): JsonResponse
    {
        $request->validate([
            'collection' => 'required|string',
            'provider' => 'required|string',
        ]);

        $provider = $request->input('provider');
        $collectionId = $request->input('collection');

        if (! $collectionId) {
            return $this->errorResponse('Collection identifier is required.', 400);
        }

        $collection = Collection::query()
            ->where('id', $collectionId)
            ->orWhere('name', $collectionId)
            ->firstOrFail();

        $redirectUrl = $this->oauthService->getRedirectUrl($collection, $provider);

        return $this->successResponse([
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function callback(Request $request): mixed
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $result = $this->oauthService->handleCallback(
            $request->input('state'),
            $request->boolean('native'),
        );

        $payload = [
            'code' => $result['code'],
            'exchange_code' => $result['code'],
            'redirect_uri' => $result['redirect_uri'] ?? null,
        ];

        if ($request->wantsJson() || app()->environment('testing')) {
            return $this->successResponse($payload);
        }

        return view('oauth.callback', $payload);
    }

    public function exchange(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $tokenData = $this->oauthService->exchangeCode(
            $request->input('code'),
        );

        return $this->successResponse([
            'token' => $tokenData->token,
            'expires_in' => $tokenData->expires_in,
            'collection_name' => $tokenData->collection_name,
            'collection_id' => $tokenData->collection_id,
            'record' => $tokenData->record,
        ]);
    }
}
