<?php

namespace Veloquent\Core\Domain\Auth\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\Actions\LoginAction;
use Veloquent\Core\Domain\Auth\Support\Fingerprint;
use Veloquent\Core\Domain\Auth\Actions\LogoutAction;
use Veloquent\Core\Domain\Auth\Requests\LoginRequest;
use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Auth\ValueObjects\RequestMetadata;
use Veloquent\Core\Domain\Auth\Actions\LogoutAllAction;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Support\Http\Controllers\ApiController;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Auth\Actions\ConfirmEmailChangeAction;
use Veloquent\Core\Domain\Auth\Actions\RequestEmailChangeAction;
use Veloquent\Core\Domain\Auth\Actions\ConfirmPasswordResetAction;
use Veloquent\Core\Domain\Auth\Actions\RequestPasswordResetAction;
use Veloquent\Core\Domain\Auth\Requests\ConfirmEmailChangeRequest;
use Veloquent\Core\Domain\Auth\Requests\RequestEmailChangeRequest;
use Veloquent\Core\Domain\Auth\Requests\ConfirmPasswordResetRequest;
use Veloquent\Core\Domain\Auth\Requests\RequestPasswordResetRequest;
use Veloquent\Core\Domain\Auth\Actions\ConfirmEmailVerificationAction;
use Veloquent\Core\Domain\Auth\Actions\RequestEmailVerificationAction;
use Veloquent\Core\Domain\Auth\Requests\ConfirmEmailVerificationRequest;

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
            $request->only(['identity', 'password']),
            RequestMetadata::fromRequest($request),
            $request
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
