<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;

class LogoutAllAction
{
    public function __construct(private TokenAuthService $tokenService, private RealtimeBusDriver $realtimeBus) {}

    /**
     * Revoke all tokens for the user, delete realtime subscriptions and
     * publish a logoutAll realtime message.
     */
    public function execute(Record $user): void
    {
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
    }
}
