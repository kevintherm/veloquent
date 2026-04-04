<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Auth\Actions\ConfirmEmailChangeAction;
use App\Domain\Auth\Actions\ConfirmEmailVerificationAction;
use App\Domain\Auth\Actions\ConfirmPasswordResetAction;
use App\Domain\Auth\Actions\LoginAction;
use App\Domain\Auth\Actions\LogoutAction;
use App\Domain\Auth\Actions\LogoutAllAction;
use App\Domain\Auth\Actions\RequestEmailChangeAction;
use App\Domain\Auth\Actions\RequestEmailVerificationAction;
use App\Domain\Auth\Actions\RequestPasswordResetAction;
use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Auth\ValueObjects\TokenData;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Http\Requests\Auth\ConfirmEmailChangeRequest;
use App\Http\Requests\Auth\ConfirmEmailVerificationRequest;
use App\Http\Requests\Auth\ConfirmPasswordResetRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestEmailChangeRequest;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(
        private LoginAction $loginAction,
        private LogoutAction $logoutAction,
        private LogoutAllAction $logoutAllAction,
        private RequestPasswordResetAction $requestPasswordResetAction,
        private ConfirmPasswordResetAction $confirmPasswordResetAction,
        private RequestEmailVerificationAction $requestEmailVerificationAction,
        private ConfirmEmailVerificationAction $confirmEmailVerificationAction,
        private RequestEmailChangeAction $requestEmailChangeAction,
        private ConfirmEmailChangeAction $confirmEmailChangeAction,
        private TokenAuthService $tokenAuthService,
    ) {}

    public function impersonate(Collection $collection, string $recordId): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        $record = Record::of($collection)->find($recordId);

        if (! $record) {
            return $this->errorResponse('Record does not belong to this collection.', Response::HTTP_NOT_FOUND);
        }

        $tokenData = $this->tokenAuthService->generateToken($record);

        return $this->tokenResponse($tokenData, Response::HTTP_OK);
    }

    public function user(): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->successResponse($user);
    }

    public function login(LoginRequest $request, Collection $collection): JsonResponse
    {
        $tokenData = $this->loginAction->execute(
            $collection,
            $request->only(['identity', 'password'])
        );

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

        $this->logoutAction->execute($request, $user);

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

        $this->logoutAllAction->execute($user);

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function me(Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->successResponse($user);
    }

    public function requestPasswordReset(RequestPasswordResetRequest $request, Collection $collection): JsonResponse
    {
        $this->requestPasswordResetAction->execute($collection, $request->only(['email']));

        return $this->successResponse([], 'If the email exists, a reset code has been sent.');
    }

    public function confirmPasswordReset(ConfirmPasswordResetRequest $request, Collection $collection): JsonResponse
    {
        $this->confirmPasswordResetAction->execute($collection, $request->only(['email', 'token', 'new_password']));

        return $this->successResponse([], 'Password has been reset successfully.');
    }

    public function requestEmailVerification(Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $this->requestEmailVerificationAction->execute($collection, $user);

        return $this->successResponse([], 'Verification code has been sent.');
    }

    public function confirmEmailVerification(ConfirmEmailVerificationRequest $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $this->confirmEmailVerificationAction->execute($collection, $user, $request->only(['token']));

        return $this->successResponse([], 'Email verified successfully.');
    }

    public function requestEmailChange(RequestEmailChangeRequest $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $this->requestEmailChangeAction->execute($collection, $user, $request->only(['new_email']));

        return $this->successResponse([], 'A verification code has been sent to your new email address.');
    }

    public function confirmEmailChange(ConfirmEmailChangeRequest $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $this->confirmEmailChangeAction->execute($collection, $user, $request->only(['token', 'new_email']));

        return $this->successResponse([], 'Email address updated successfully.');
    }

    private function userMatchesCollection(Record $user, Collection $collection): bool
    {
        return $user->collection?->id === $collection->id;
    }

    private function tokenResponse(TokenData $tokenData, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse(
            [
                'token' => $tokenData->token,
                'expires_in' => $tokenData->expires_in,
                'collection_name' => $tokenData->collection_name,
            ],
            'Success',
            $code
        );
    }
}
