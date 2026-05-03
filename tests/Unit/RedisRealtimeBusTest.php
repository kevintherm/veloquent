<?php

use Veloquent\Core\Domain\Realtime\Bus\RedisRealtimeBus;
use Illuminate\Support\Facades\Redis;

it('publishes payloads with invalid utf-8 content', function () {
    $payload = [
        'type' => 'record_event',
        'record' => [
            'name' => "Bad\xB1", // invalid UTF-8 byte
        ],
    ];

    $publishedMessage = null;

    $connection = Mockery::mock();
    $connection->shouldReceive('xadd')
        ->once()
        ->andReturnUsing(function ($stream, $id, $data) use (&$publishedMessage) {
            $publishedMessage = $data['data'];
            return '123-0';
        });

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
