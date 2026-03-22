<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Actions\CreateRecordAction;
use App\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

it('denies create when collection create api rule is null', function () {
    Auth::logout();

    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [
        ['name' => 'title', 'type' => 'text', 'nullable' => false, 'unique' => false],
    ];
    $collection->api_rules = [
        'list' => '',
        'view' => '',
        'create' => null,
        'update' => '',
        'delete' => '',
    ];

    expect(fn () => resolve(CreateRecordAction::class)->execute($collection, ['title' => 'Hello']))
        ->toThrow(AuthorizationException::class);
});

it('denies create when create api rule evaluates to false', function () {
    $userCollection = new Collection;
    $userCollection->name = 'users';
    $userCollection->type = CollectionType::Base;
    $userCollection->is_system = false;
    $userCollection->fields = [];
    $userCollection->api_rules = [];

    $user = Record::of($userCollection);
    $user->setAttribute('id', 2);

    Auth::setUser($user);

    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [
        ['name' => 'id', 'type' => 'number', 'nullable' => false, 'unique' => false],
        ['name' => 'title', 'type' => 'text', 'nullable' => false, 'unique' => false],
    ];
    $collection->api_rules = [
        'list' => '',
        'view' => '',
        'create' => 'id = @request.auth.id',
        'update' => '',
        'delete' => '',
    ];

    expect(fn () => resolve(CreateRecordAction::class)->execute($collection, ['id' => 1, 'title' => 'Hello']))
        ->toThrow(AuthorizationException::class);
});
