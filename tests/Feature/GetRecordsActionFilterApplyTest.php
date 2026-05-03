<?php

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\Records\Models\Record;

it('applies filter in get records action query', function () {
    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [
        ['name' => 'id', 'type' => 'number', 'nullable' => false, 'unique' => true],
    ];
    $collection->api_rules = [
        'list' => '',
        'view' => '',
        'create' => '',
        'update' => '',
        'delete' => '',
    ];

    $query = Record::of($collection)->newQuery();
    $baseSql = $query->toSql();

    QueryFilter::for($query, ['id'])->run('id = null');

    expect(strtolower($query->toSql()))->not->toBe(strtolower($baseSql))
        ->and(strtolower($query->toSql()))->toContain('is null');
});
