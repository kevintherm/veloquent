<?php

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\ResolvesRuleContextRelations;
use Veloquent\Core\Domain\Records\Services\RuleContextBuilder;
use Veloquent\Core\Domain\Records\Services\UpdateRuleContextBuilder;
use Illuminate\Http\Request;

it('builds update rule context with record and request', function () {
    $collection = new Collection;
    $collection->fields = [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'status', 'type' => 'text'],
    ];

    // Use Reflection to allow direct instantiation of Record
    $reflection = new ReflectionProperty(Record::class, 'allowDirectInstantiation');
    $reflection->setAccessible(true);
    $reflection->setValue(null, true);

    $record = new Record;
    $record->setRawAttributes([
        'id' => 1,
        'title' => 'Old Title',
        'status' => 'draft',
    ]);

    $user = new Record;
    $user->setRawAttributes(['id' => 99]);

    // Reset reflection
    $reflection->setValue(null, false);

    $request = Request::create('/api/update', 'PATCH', ['title' => 'New Title']);

    $context = (new UpdateRuleContextBuilder(new RuleContextBuilder, new ResolvesRuleContextRelations))->build(
        $collection,
        ['record' => $record, 'data' => ['title' => 'New Title']],
        $user,
        $request
    );

    expect($context['title'])->toBe('Old Title')
        ->and(data_get($context, 'request.body.title'))->toBe('New Title')
        ->and($context['status'])->toBe('draft')
        ->and(data_get($context, 'request.auth.id'))->toBe(99);
});
