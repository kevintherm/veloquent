<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('{authCollection}.{subscriberId}', function ($user, string $authCollection, string $subscriberId): bool {
    return $user !== null
        && method_exists($user, 'getTable')
        && method_exists($user, 'getKey')
        && $user->getTable() === $authCollection
        && (string) $user->getKey() === $subscriberId;
});
