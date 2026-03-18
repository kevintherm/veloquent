<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRealtimePayloadCollection(string $name): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => $name,
        'type' => CollectionType::Base->value,
        'description' => ucfirst($name).' collection',
        'fields' => [
            ['name' => 'name', 'type' => 'text', 'nullable' => false, 'unique' => false],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
    ]);
}

it('publishes created event with collection id for realtime worker', function () {
    $publishedPayloads = [];

    app()->instance(RealtimeBusDriver::class, new class($publishedPayloads) implements RealtimeBusDriver
    {
        /**
         * @param  array<int, array<string, mixed>>  $payloads
         */
        public function __construct(private array &$payloads) {}

        public function publish(array $payload): void
        {
            $this->payloads[] = $payload;
        }

        public function listen(callable $callback, Closure $shouldStop): void
        {
            // Not needed in this observer payload test.
        }
    });

    $collection = createRealtimePayloadCollection('realtime_payload_users');

    $record = Record::of($collection)->create([
        'name' => 'Realtime Payload Test',
    ]);

    expect($record->id)->not->toBeNull();
    expect($publishedPayloads)->toHaveCount(1);

    expect($publishedPayloads[0])->toMatchArray([
        'type' => 'record_event',
        'event' => 'created',
        'collection_id' => $collection->id,
    ]);
    expect($publishedPayloads[0]['record'])->toBeArray();
    expect($publishedPayloads[0]['record']['id'])->toBe($record->id);
});
