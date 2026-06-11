<?php

use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function createDateSerializationTestCollection(): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => 'dates_'.Str::lower(Str::random(8)),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'my_date', 'type' => CollectionFieldType::Date->value, 'nullable' => true],
            ['name' => 'my_datetime', 'type' => CollectionFieldType::Datetime->value, 'nullable' => true],
        ],
        'api_rules' => [
            'list' => '', 'create' => '', 'view' => '', 'update' => '', 'delete' => '',
        ],
    ]);
}

it('serializes date fields as Y-m-d and datetime fields as UTC ISO-8601', function () {
    $collection = createDateSerializationTestCollection();

    $dateVal = '2026-06-11';
    $datetimeVal = '2026-06-11 15:30:45';

    // 1. Test creation and serialization in JSON response
    $response = postJson("/api/collections/{$collection->id}/records", [
        'my_date' => $dateVal,
        'my_datetime' => $datetimeVal,
    ])->assertCreated();

    // The API returns the record data
    $data = $response->json('data');

    // my_date should be serialized as Y-m-d (e.g. '2026-06-11')
    expect($data['my_date'])->toBe('2026-06-11');
    expect($data['my_datetime'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
    
    // 2. Test fetching record from DB and calling toArray() directly
    $recordId = $data['id'];
    $record = Record::of($collection)->findOrFail($recordId);

    $arrayData = $record->toArray();

    expect($arrayData['my_date'])->toBe('2026-06-11');
    expect($arrayData['my_datetime'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');

    // Verify created_at timestamp is also serialized as datetime
    expect($arrayData['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});
