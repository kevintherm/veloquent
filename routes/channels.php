<?php

use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('{authCollection}.{subscriberId}', function ($user, string $authCollection, string $subscriberId): bool {
    return $user instanceof Record
        && $user->getTable() === $authCollection
        && (string) $user->getKey() === $subscriberId;
});
