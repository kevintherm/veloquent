<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\CreateRuleContextBuilder;
use Illuminate\Http\Request;

it('builds create rule context with request and collection fields', function () {
    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [
        ['name' => 'title', 'type' => 'text', 'nullable' => false, 'unique' => false],
        ['name' => 'status', 'type' => 'text', 'nullable' => true, 'unique' => false],
    ];

    $authCollection = new Collection;
    $authCollection->name = 'users';
    $authCollection->type = CollectionType::Base;
    $authCollection->is_system = false;
    $authCollection->fields = [];
    $authCollection->api_rules = [];

    $user = Record::of($authCollection);
    $user->setAttribute('id', 99);
    $user->setAttribute('email', 'user@example.com');

    $request = Request::create('/api/collections/posts/records?search=test', 'POST', [
        'title' => 'Hello',
    ]);

    $context = (new CreateRuleContextBuilder)->build(
        $collection,
        ['title' => 'Hello'],
        $user,
        $request
    );

    expect(data_get($context, 'request.body.title'))->toBe('Hello')
        ->and(data_get($context, 'request.query.search'))->toBe('test')
        ->and(data_get($context, 'request.auth.id'))->toBe(99)
        ->and($context['title'])->toBe('Hello')
        ->and($context['status'])->toBeNull();
});
