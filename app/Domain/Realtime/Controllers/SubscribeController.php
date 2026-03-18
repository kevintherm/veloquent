<?php

namespace App\Domain\Realtime\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Bus\RealtimeBusDriver;
use App\Http\Controllers\Controller;
use App\Models\RealtimeSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function subscribe(Request $request, Collection $collection): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'max:500'],
        ]);

        $authCollection = Auth::user()->getTable();
        $subscriberId = (string) Auth::user()->getKey();
        $channel = 'private-'.$authCollection.'.'.$subscriberId;

        dispatch(function () use ($collection, $authCollection, $subscriberId, $channel, $validated): void {
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
            ]);
        })->afterResponse();

        return response()->json([
            'status' => 'subscribed',
            'channel' => $channel,
        ]);
    }

    public function unsubscribe(Request $request, Collection $collection): JsonResponse
    {
        $authCollection = Auth::user()->getTable();
        $subscriberId = (string) Auth::user()->getKey();
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
