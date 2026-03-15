<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Auth;

function makeCollection(array $apiRules): Collection
{
    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [
        ['name' => 'status', 'type' => 'text'],
    ];
    $collection->api_rules = $apiRules;

    return $collection;
}

function makeSuperuserRecord(): Record
{
    $collection = new Collection;
    $collection->name = 'superusers';
    $collection->type = CollectionType::Base;
    $collection->is_system = true;
    $collection->fields = [];
    $collection->api_rules = [];

    return Record::of($collection);
}

function makeRegularRecord(): Record
{
    $collection = new Collection;
    $collection->name = 'users';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [];
    $collection->api_rules = [];

    return Record::of($collection);
}

it('still applies collection api rule in builder even for authenticated superuser', function () {
    Auth::setUser(makeSuperuserRecord());

    $sql = Record::of(makeCollection(['list' => null]))
        ->applyRule('list')
        ->toSql();

    expect(strtolower($sql))->toContain(' where ')
        ->and($sql)->toContain('1 = 0');
});

it('still applies collection api rule for non superuser', function () {
    Auth::setUser(makeRegularRecord());

    $sql = Record::of(makeCollection(['list' => null]))
        ->applyRule('list')
        ->toSql();

    expect(strtolower($sql))->toContain(' where ')
        ->and($sql)->toContain('1 = 0');
});
