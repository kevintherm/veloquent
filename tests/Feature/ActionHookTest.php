<?php

use Veloquent\Core\Domain\Hooks\Facades\Hooks;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Actions\CreateRecordAction;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    app()->forgetInstance(\Veloquent\Core\Domain\Hooks\HookRegistry::class);
    app()->singleton(\Veloquent\Core\Domain\Hooks\HookRegistry::class);
});

it('executes before and after hooks in CreateRecordAction', function () {
    $userCollection = new Collection;
    $userCollection->name = 'superusers';
    $userCollection->type = CollectionType::Auth;
    $userCollection->is_system = true;
    $userCollection->table_name = 'superusers';

    $user = Record::of($userCollection);
    Auth::setUser($user);

    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [['name' => 'title', 'type' => 'text']];
    $collection->api_rules = ['create' => ''];

    $beforeCalled = false;
    $afterCalled = false;

    Hooks::before('record.create', function (HookPayload $payload, Closure $next) use (&$beforeCalled) {
        $beforeCalled = true;
        $payload->data['title'] = 'Hooked Title';
        return $next($payload);
    });

    Hooks::after('record.create', function (HookPayload $payload, Closure $next) use (&$afterCalled) {
        $afterCalled = true;
        return $next($payload);
    });

    Schema::create($collection->getPhysicalTableName(), function ($table) {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    $record = resolve(CreateRecordAction::class)->execute($collection, ['title' => 'Original Title']);

    expect($beforeCalled)->toBeTrue();
    expect($afterCalled)->toBeTrue();
    expect($record->title)->toBe('Hooked Title');
});

it('aborts operation when HookAbortException is thrown', function () {
    $userCollection = new Collection;
    $userCollection->name = 'superusers';
    $userCollection->type = CollectionType::Auth;
    $userCollection->is_system = true;
    $userCollection->table_name = 'superusers';

    $user = Record::of($userCollection);
    Auth::setUser($user);

    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [['name' => 'title', 'type' => 'text']];
    $collection->api_rules = ['create' => ''];

    Hooks::before('record.create', function () {
        throw new \Veloquent\Core\Domain\Hooks\Exceptions\HookAbortException('Aborted by hook');
    });

    Schema::create($collection->getPhysicalTableName(), function ($table) {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    expect(fn () => resolve(CreateRecordAction::class)->execute($collection, ['title' => 'Title']))
        ->toThrow(\Veloquent\Core\Domain\Hooks\Exceptions\HookAbortException::class, 'Aborted by hook');

    expect(Record::of($collection)->count())->toBe(0);
});

it('rolls back database changes if a before hook fails', function () {
    $userCollection = new Collection;
    $userCollection->name = 'superusers';
    $userCollection->type = CollectionType::Auth;
    $userCollection->is_system = true;
    $userCollection->table_name = 'superusers';
    $user = Record::of($userCollection);
    Auth::setUser($user);

    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [['name' => 'title', 'type' => 'text']];
    $collection->api_rules = ['create' => ''];

    Schema::create($collection->getPhysicalTableName(), function ($table) {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    // We register a hook that throws after a manual DB change
    Hooks::before('record.create', function (HookPayload $payload, Closure $next) use ($collection) {
        // Manually insert something to see if it rolls back
        DB::table($collection->getPhysicalTableName())->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'title' => 'Should be rolled back',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        throw new \Veloquent\Core\Domain\Hooks\Exceptions\HookAbortException('Aborted');
    });

    try {
        resolve(CreateRecordAction::class)->execute($collection, ['title' => 'New Post']);
    } catch (\Veloquent\Core\Domain\Hooks\Exceptions\HookAbortException $e) {
    }

    expect(DB::table($collection->getPhysicalTableName())->count())->toBe(0);
});
