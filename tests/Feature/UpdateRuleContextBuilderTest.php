<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\UpdateRuleContextBuilder;
use Illuminate\Http\Request;

it('builds update rule context with record, request and merged data', function () {
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

    $context = (new UpdateRuleContextBuilder)->build(
        $collection,
        $record,
        ['title' => 'New Title'],
        $user,
        $request
    );

    expect($context['title'])->toBe('New Title')
        ->and($context['status'])->toBe('draft')
        ->and($context['record']['title'])->toBe('Old Title')
        ->and(data_get($context, 'request.auth.id'))->toBe(99);
});
