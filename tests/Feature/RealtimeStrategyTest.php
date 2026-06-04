<?php

namespace Tests\Feature;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Realtime\Services\RealtimeBuffer;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Realtime\Services\DefaultRealtimeDispatcher;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Observers\RecordObserver;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Veloquent\Core\Domain\Realtime\Jobs\ProcessRealtimeEventJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Broadcast;
use Mockery;
use Exception;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::withoutEvents(fn () => Tenant::query()->create([
        'name' => 'Test Tenant',
        'domain' => Str::random(10) . '.test',
        'database' => null,
    ]));

    app()->instance((string) config('multitenancy.current_tenant_container_key'), $this->tenant);
    
    $this->collection = Collection::withoutEvents(fn () => Collection::query()->create([
        'id' => (string) Str::ulid(),
        'name' => 'test_posts',
        'type' => CollectionType::Base,
        'table_name' => '_velo_test_posts',
    ]));
});

afterEach(function (): void {
    app()->forgetInstance((string) config('multitenancy.current_tenant_container_key'));
    app()->forgetInstance(RealtimeDispatcher::class);
    Mockery::close();
});

it('buffers events in after_response strategy', function () {
    config(['velo.realtime.strategy' => 'after_response']);
    
    $record = Record::fromTable('_velo_test_posts');
    $record->collection = $this->collection;
    $record->forceFill([
        'id' => (string) Str::ulid(),
        'name' => 'Buffered Event Test',
    ]);
    
    $observer = app(RecordObserver::class);
    $observer->created($record);
    
    $buffer = app(RealtimeBuffer::class);
    expect($buffer->isEmpty())->toBeFalse();
    
    // Test flushing
    $dispatcher = Mockery::mock(DefaultRealtimeDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->with(Mockery::type(RealtimeRecordEvent::class))->once();
    
    $buffer->flush($dispatcher);
    expect($buffer->isEmpty())->toBeTrue();
});

it('dispatches events immediately in sync strategy', function () {
    config(['velo.realtime.strategy' => 'sync']);
    
    $dispatcher = Mockery::mock(DefaultRealtimeDispatcher::class)->makePartial();
    $dispatcher->shouldReceive('dispatch')->with(Mockery::type(RealtimeRecordEvent::class))->once();
    app()->instance(RealtimeDispatcher::class, $dispatcher);
    
    $record = Record::fromTable('_velo_test_posts');
    $record->collection = $this->collection;
    $record->forceFill([
        'id' => (string) Str::ulid(),
        'name' => 'Sync Event Test',
    ]);
    
    $observer = app(RecordObserver::class);
    $observer->created($record);
});

it('dispatches retry job on failure in sync strategy', function () {
    Bus::fake();
    config(['velo.realtime.strategy' => 'sync']);
    
    $dispatcher = Mockery::mock(DefaultRealtimeDispatcher::class)->makePartial();
    // Use shouldAllowMockingProtectedMethods() to mock protected method
    $dispatcher->shouldAllowMockingProtectedMethods();
    $dispatcher->shouldReceive('loadSubscriptionsFromLandlord')->andThrow(new Exception('DB failed'));
    app()->instance(RealtimeDispatcher::class, $dispatcher);
    
    $event = new RealtimeRecordEvent(
        tenantId: $this->tenant->id,
        collectionId: $this->collection->id,
        record: ['id' => '1'],
        event: 'created',
    );
    
    $dispatcher->dispatch($event);
    
    Bus::assertDispatched(ProcessRealtimeEventJob::class);
});
