<?php

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Validators\CollectionFieldValidator;
use Illuminate\Validation\ValidationException;
use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Records\Actions\CreateRecordAction;
use Veloquent\Core\Domain\Records\Actions\GetRecordsAction;

it('validates select field options', function () {
    $validator = app(CollectionFieldValidator::class);

    // Missing options
    try {
        $validator->validateForCreate([
            ['name' => 'status', 'type' => 'select']
        ], [], false);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.0.options');
    }

    // Invalid options (not an array)
    try {
        $validator->validateForCreate([
            ['name' => 'status', 'type' => 'select', 'options' => 'invalid']
        ], [], false);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.0.options');
    }

    // Empty options
    try {
        $validator->validateForCreate([
            ['name' => 'status', 'type' => 'select', 'options' => []]
        ], [], false);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.0.options');
    }

    // Invalid option structure (still an array of objects)
    try {
        $validator->validateForCreate([
            ['name' => 'status', 'type' => 'select', 'options' => [['label' => 'Published', 'value' => 'pub']]]
        ], [], false);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.0.options.0');
    }

    // Option value too long (> 255)
    try {
        $validator->validateForCreate([
            ['name' => 'status', 'type' => 'select', 'options' => [
                str_repeat('a', 256)
            ]]
        ], [], false);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.0.options.0');
    }

    // Valid select field
    $validator->validateForCreate([
        ['name' => 'status', 'type' => 'select', 'options' => [
            'published',
            'draft',
        ]]
    ], [], false);
});

it('can create a collection with a select field and add records', function () {
    $createAction = app(CreateCollectionAction::class);
    
    $collection = $createAction->execute([
        'name' => 'posts',
        'type' => 'base',
        'api_rules' => [
            'list' => 'status != null',
            'view' => 'status != null',
            'create' => 'status != null',
            'update' => 'status != null',
            'delete' => 'status != null',
        ],
        'fields' => [
            [
                'name' => 'status',
                'type' => 'select',
                'options' => [
                    'published',
                    'draft',
                ]
            ]
        ]
    ]);

    expect(collect($collection->fields)->contains(fn($f) => 
        $f['name'] === 'status' && 
        $f['type'] === 'select' && 
        count($f['options']) === 2
    ))->toBeTrue();

    // Add a record
    $createRecordAction = app(CreateRecordAction::class);
    $record = $createRecordAction->execute($collection, [
        'status' => 'published'
    ]);

    expect($record->status)->toBe('published');

    // Retrieve records
    $getRecordsAction = app(GetRecordsAction::class);
    $records = $getRecordsAction->execute($collection, 'created_at', '', 15);
    
    expect($records->items())->toHaveCount(1)
        ->and($records->items()[0]->status)->toBe('published');
});
