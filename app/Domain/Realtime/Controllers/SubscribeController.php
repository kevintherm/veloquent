<?php

namespace App\Domain\Realtime\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Actions\SubscribeToCollectionAction;
use App\Domain\Realtime\Actions\UnsubscribeFromCollectionAction;
use App\Domain\Records\Models\Record;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscribeController extends ApiController
{
    public function __construct(
        private readonly SubscribeToCollectionAction $subscribeAction,
        private readonly UnsubscribeFromCollectionAction $unsubscribeAction,
    ) {}

    public function subscribe(Request $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user instanceof Record) {
            throw new AuthenticationException;
        }

        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->subscribeAction->execute($user, $collection, $validated['filter'] ?? null);

        return $this->successResponse([
            'status' => 'subscribed',
            'channel' => $result['channel'],
            'expires_at' => $result['expires_at'],
        ]);
    }

    public function unsubscribe(Request $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user instanceof Record) {
            throw new AuthenticationException;
        }

        $this->unsubscribeAction->execute($user, $collection);

        return $this->successResponse(['status' => 'unsubscribed']);
    }
}
