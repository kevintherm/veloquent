<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;
use App\Http\Requests\Auth\LoginRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(
        private TokenAuthService $tokenService,
        private RealtimeBusDriver $realtimeBus,
    ) {}

    public function login(LoginRequest $request, Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        $credentials = $request->only('email', 'password');

        $user = Record::of($collection)->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenData = $this->tokenService->generateToken($user);

        return $this->tokenResponse($tokenData);
    }

    public function logout(Request $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if ($user && ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $user || ! $user->collection?->id) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenHash = hash('sha256', $this->tokenService->extractTokenFromRequest($request));
        $this->tokenService->revokeRecordTokens($user->collection->id, $user->id, $tokenHash);

        $this->realtimeBus->publish([
            'type' => 'connection',
            'action' => 'logout',
            'auth_collection' => $user->getTable(),
            'subscriber_id' => (string) $user->getKey(),
        ]);

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function logoutAll(Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if ($user && ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $user || ! $user->collection?->id) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $this->tokenService->revokeRecordTokens($user->collection->id, $user->id);

        RealtimeSubscription::query()
            ->where('subscriber_id', (string) $user->getKey())
            ->delete();

        $this->realtimeBus->publish([
            'type' => 'connection',
            'action' => 'logoutAll',
            'auth_collection' => $user->getTable(),
            'subscriber_id' => (string) $user->getKey(),
        ]);

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function me(Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->successResponse($user);
    }

    private function userMatchesCollection(Record $user, Collection $collection): bool
    {
        return $user->collection?->id === $collection->id;
    }

    /**
     * @param  array{token: string, expires_in: int, collection_name: string}  $tokenData
     */
    private function tokenResponse(array $tokenData, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse(
            [
                'token' => $tokenData['token'],
                'expires_in' => $tokenData['expires_in'],
                'collection_name' => $tokenData['collection_name'],
            ],
            'Success',
            $code
        );
    }
}
