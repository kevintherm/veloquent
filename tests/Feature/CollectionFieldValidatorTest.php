<?php

use App\Domain\Collections\Validators\CollectionFieldValidator;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Validation\ValidationException;

it('collects create validation errors for reserved names duplicates and invalid indexes', function () {
    $validator = app(CollectionFieldValidator::class);

    $fields = [
        ['name' => 'id', 'type' => 'text'],
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'broken', 'type' => 'not_a_type'],
    ];

    $indexes = [
        ['columns' => ['title', 'id'], 'type' => 'index'],
        ['columns' => ['id', 'title'], 'type' => 'index'],
        ['columns' => ['missing'], 'type' => 'index'],
    ];

    try {
        $validator->validateForCreate($fields, $indexes, false);

        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys([
            'fields.0.name',
            'fields.2.name',
            'fields.3.type',
            'indexes.1',
            'indexes.2.columns.0',
        ]);
    }
});

it('rejects changing type for known field ids on update', function () {
    $validator = app(CollectionFieldValidator::class);

    $storedFields = [
        ['id' => 'known001', 'name' => 'title', 'type' => 'text', 'nullable' => false, 'unique' => false],
    ];

    $incomingFields = [
        ['id' => 'known001', 'name' => 'title', 'type' => 'number', 'nullable' => false, 'unique' => false],
    ];

    try {
        $validator->validateForUpdate($incomingFields, $storedFields, [], false);

        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.0.type');
    }
});

it('rejects dropping auth reserved fields on auth collections', function () {
    $validator = app(CollectionFieldValidator::class);

    $storedFields = collect(array_values(SchemaChangePlan::getReservedFieldDefinitions(true)))
        ->values()
        ->map(function (array $field, int $index): array {
            $field['id'] = sprintf('stored%02d', $index);

            return $field;
        })
        ->all();

    try {
        $validator->validateForUpdate([], $storedFields, [], true);

        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('fields.3')
            ->and($e->errors())->toHaveKey('fields.4')
            ->and($e->errors())->toHaveKey('fields.5')
            ->and($e->errors())->toHaveKey('fields.6');
    }
});
