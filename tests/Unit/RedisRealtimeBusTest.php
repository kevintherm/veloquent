<?php

use App\Domain\Realtime\Bus\RedisRealtimeBus;
use Illuminate\Support\Facades\Redis;
use Mockery;

it('publishes payloads with invalid utf-8 content', function () {
    $payload = [
        'type' => 'record_event',
        'record' => [
            'name' => "Bad\xB1", // invalid UTF-8 byte
        ],
    ];

    $publishedMessage = null;

    $connection = Mockery::mock();
    $connection->shouldReceive('publish')
        ->once()
        ->with('realtime:events', Mockery::on(function ($message) use (&$publishedMessage): bool {
            $publishedMessage = $message;

            return is_string($message);
        }))
        ->andReturn(1);

    Redis::shouldReceive('connection')
        ->once()
        ->with('realtime')
        ->andReturn($connection);

    (new RedisRealtimeBus)->publish($payload);

    $decoded = json_decode((string) $publishedMessage, true);

    expect($decoded)->toBeArray()
        ->and($decoded['type'])->toBe('record_event')
        ->and($decoded['record']['name'])->toBeString();
});
