<?php

namespace App\Domain\Realtime\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Records\Models\Record;
use App\Http\Controllers\Controller;
use App\Models\RealtimeSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function subscribe(Request $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user instanceof Record) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'max:500'],
        ]);

        $authCollection = $user->getTable();
        $subscriberId = (string) $user->getKey();
        $channel = 'private-'.$authCollection.'.'.$subscriberId;
        $subscriptionTtl = max(1, (int) config('velo.realtime.subscription_ttl', 120));
        $expiresAt = CarbonImmutable::now()->addSeconds($subscriptionTtl);

        dispatch(function () use ($collection, $authCollection, $subscriberId, $channel, $validated, $expiresAt): void {
            $subscription = RealtimeSubscription::query()->updateOrCreate(
                [
                    'collection_id' => $collection->id,
                    'auth_collection' => $authCollection,
                    'subscriber_id' => $subscriberId,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'channel' => $channel,
                    'filter' => (string) ($validated['filter'] ?? ''),
                    'expired_at' => $expiresAt,
                ]
            );

            app(RealtimeBusDriver::class)->publish([
                'type' => 'connection',
                'action' => 'subscribe',
                'collection_id' => $collection->id,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'filter' => $subscription->filter,
                'channel' => $channel,
                'expired_at' => $expiresAt->toIso8601String(),
            ]);
        })->afterResponse();

        return response()->json([
            'status' => 'subscribed',
            'channel' => $channel,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function unsubscribe(Request $request, Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user instanceof Record) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $authCollection = $user->getTable();
        $subscriberId = (string) $user->getKey();
        $channel = 'private-'.$authCollection.'.'.$subscriberId;

        dispatch(function () use ($collection, $authCollection, $subscriberId, $channel): void {
            RealtimeSubscription::query()->where([
                'collection_id' => $collection->id,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
            ])->delete();

            app(RealtimeBusDriver::class)->publish([
                'type' => 'connection',
                'action' => 'unsubscribe',
                'collection_id' => $collection->id,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'channel' => $channel,
            ]);
        })->afterResponse();

        return response()->json(['status' => 'unsubscribed']);
    }
}
