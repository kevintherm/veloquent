<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Services\OtpService;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;
use App\Http\Requests\Auth\ConfirmEmailVerificationRequest;
use App\Http\Requests\Auth\ConfirmPasswordResetRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(
        private TokenAuthService $tokenService,
        private RealtimeBusDriver $realtimeBus,
        private OtpService $otpService,
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
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        $user = Record::of($collection)->where('email', $request->input('email'))->first();

        if ($user) {
            $this->otpService->issue($user, OtpAction::PasswordReset, $collection);
        }

        return $this->successResponse([], 'If the email exists, a reset code has been sent.');
    }

    public function confirmPasswordReset(ConfirmPasswordResetRequest $request, Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        $user = Record::of($collection)->where('email', $request->input('email'))->first();

        if (! $user) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        return DB::transaction(function () use ($request, $collection, $user) {
            $this->otpService->consume(
                $request->input('token'),
                OtpAction::PasswordReset,
                $collection,
                (string) $user->id,
            );

            Record::of($collection)
                ->where('id', $user->id)
                ->update(['password' => Hash::make($request->input('password'))]);

            $this->tokenService->revokeRecordTokens($collection->id, (string) $user->id);

            return $this->successResponse([], 'Password has been reset successfully.');
        });
    }

    public function requestEmailVerification(Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->hasAttribute('verified') && $user->getAttribute('verified') == true) {
            return $this->errorResponse('User email is already verified.', Response::HTTP_BAD_REQUEST);
        }

        $this->otpService->issue($user, OtpAction::EmailVerification, $collection);

        return $this->successResponse([], 'Verification code has been sent.');
    }

    public function confirmEmailVerification(ConfirmEmailVerificationRequest $request, Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $this->otpService->consume(
            $request->input('token'),
            OtpAction::EmailVerification,
            $collection,
            (string) $user->id,
        );

        Record::of($collection)
            ->where('id', $user->id)
            ->update(['verified' => true]);

        return $this->successResponse([], 'Email verified successfully.');
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
