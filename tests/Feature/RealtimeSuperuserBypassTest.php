<?php

namespace Tests\Feature;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Veloquent\Core\Domain\Records\Events\RecordChanged;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::withoutEvents(fn () => Tenant::query()->create([
        'name' => 'Test Tenant',
        'domain' => Str::random(10) . '.test',
        'database' => null,
    ]));

    app()->instance((string) config('multitenancy.current_tenant_container_key'), $this->tenant);
    
    $this->tenant->execute(function() {
        $this->superusersCollection = Collection::query()->where('table_name', 'superusers')->first() 
            ?? Collection::withoutEvents(fn () => Collection::query()->create([
                'id' => (string) Str::ulid(),
                'name' => 'superusers',
                'type' => CollectionType::Auth,
                'table_name' => 'superusers',
            ]));

        $this->postsCollection = Collection::withoutEvents(fn () => Collection::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'posts',
            'type' => CollectionType::Base,
            'table_name' => 'posts',
            'api_rules' => ['view' => 'status = "active"'],
            'fields' => [
                ['name' => 'status', 'type' => 'text'],
                ['name' => 'title', 'type' => 'text'],
            ],
        ]));
    });
});

it('can find the tenant', function () {
    $found = Tenant::findByIdCached($this->tenant->id);
    expect($found)->not->toBeNull();
});

it('correctly identifies superuser', function () {
    $superuser = $this->tenant->execute(fn() => Record::of($this->superusersCollection)->newQuery()->create([
        'id' => (string) Str::ulid(),
        'email' => 'admin2@test.com',
        'name' => 'Admin',
        'password' => 'password',
    ]));
    
    expect($superuser->isSuperuser())->toBeTrue();
});

it('has the view rule on posts collection', function () {
    $found = $this->tenant->execute(fn() => Collection::findByIdCached($this->postsCollection->id));
    expect($found->api_rules)->toHaveKey('view')
        ->and($found->api_rules['view'])->toBe('status = "active"');
});

it('bypasses view rule for superuser but still applies filter', function () {
    Event::fake([RecordChanged::class]);
    
    $superuser = $this->tenant->execute(fn() => Record::of($this->superusersCollection)->newQuery()->create([
        'id' => (string) Str::ulid(),
        'email' => 'admin@test.com',
        'name' => 'Admin',
        'password' => 'password',
    ]));

    $dispatcher = app(RealtimeDispatcher::class);
    
    // 1. Record that fails the VIEW RULE (status=inactive)
    $eventInactive = new RealtimeRecordEvent(
        tenantId: $this->tenant->id,
        collectionId: $this->postsCollection->id,
        record: ['id' => 'post-1', 'status' => 'inactive'],
        event: 'created',
    );

    $dispatcher->dispatch($eventInactive, [[
        'auth_collection' => 'superusers',
        'subscriber_id' => $superuser->id,
        'filter' => '',
        'channel' => 'ch-1',
    ]]);

    Event::assertDispatched(RecordChanged::class, function ($event) {
        return $event->record['id'] === 'post-1';
    });

    // 2. Record that matches VIEW RULE but FAILS the SUBSCRIPTION FILTER
    $eventActive = new RealtimeRecordEvent(
        tenantId: $this->tenant->id,
        collectionId: $this->postsCollection->id,
        record: ['id' => 'post-2', 'status' => 'active'],
        event: 'created',
    );

    $dispatcher->dispatch($eventActive, [[
        'auth_collection' => 'superusers',
        'subscriber_id' => $superuser->id,
        'filter' => 'title = "Special"',
        'channel' => 'ch-2',
    ]]);

    Event::assertNotDispatched(RecordChanged::class, function ($event) {
        return $event->record['id'] === 'post-2';
    });

    // 3. Record that matches BOTH
    $eventMatchesAll = new RealtimeRecordEvent(
        tenantId: $this->tenant->id,
        collectionId: $this->postsCollection->id,
        record: ['id' => 'post-3', 'status' => 'active', 'title' => 'Special'],
        event: 'created',
    );

    $dispatcher->dispatch($eventMatchesAll, [[
        'auth_collection' => 'superusers',
        'subscriber_id' => $superuser->id,
        'filter' => 'title = "Special"',
        'channel' => 'ch-3',
    ]]);

    Event::assertDispatched(RecordChanged::class, function ($event) {
        return $event->record['id'] === 'post-3';
    });
});
