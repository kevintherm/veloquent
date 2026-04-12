<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Observers\RecordObserver;
use App\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tenant = new Tenant;
    $tenant->forceFill(['id' => 1001]);

    app()->instance((string) config('multitenancy.current_tenant_container_key'), $tenant);
});

afterEach(function (): void {
    app()->forgetInstance((string) config('multitenancy.current_tenant_container_key'));
});

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

    $collection = new Collection([
        'name' => 'realtime_payload_users',
        'type' => CollectionType::Base,
        'table_name' => '_velo_realtime_payload_users',
    ]);

    $collection->forceFill([
        'id' => (string) Str::ulid(),
    ]);

    $record = Record::fromTable('_velo_realtime_payload_users');
    $record->collection = $collection;
    $record->forceFill([
        'id' => (string) Str::ulid(),
        'name' => 'Realtime Payload Test',
    ]);

    (new RecordObserver)->created($record);

    expect($publishedPayloads)->toHaveCount(1)
        ->and($publishedPayloads[0])->toMatchArray([
            'type' => 'record_event',
            'event' => 'created',
            'tenant_id' => 1001,
            'collection_id' => $collection->id,
        ]);
    expect($publishedPayloads[0]['record'])->toBeArray()
        ->and($publishedPayloads[0]['record']['id'])->toBe($record->id);
});
