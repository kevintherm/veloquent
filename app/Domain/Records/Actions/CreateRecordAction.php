<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class CreateRecordAction
{
    public function execute(Collection $collection, array $data): array
    {
        // Validate data against collection fields
        $this->validateData($collection, $data);

        try {
            $record = Record::forCollection($collection);
            $created = $record->create($data);

            return $created->toArray();
        } catch (QueryException $e) {
            throw new \Exception("Failed to create record in table {$record->getTable()}: ".$e->getMessage());
        }
    }

    private function validateData(Collection $collection, array $data): void
    {
        $rules = [];
        $fields = $collection->fields ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $rule = [];

            if (! $field['nullable'] ?? false) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }

            if ($field['unique'] ?? false) {
                $rule[] = 'unique:'.$collection->getPhysicalTableName().','.$fieldName;
            }

            if ($field['type'] === 'string' && isset($field['length'])) {
                $rule[] = 'max:'.$field['length'];
            }

            if ($field['type'] === 'email') {
                $rule[] = 'email';
            }

            if ($field['type'] === 'integer') {
                $rule[] = 'integer';
            }

            if ($field['type'] === 'boolean') {
                $rule[] = 'boolean';
            }

            if ($field['type'] === 'date') {
                $rule[] = 'date';
            }

            if ($field['type'] === 'datetime') {
                $rule[] = 'datetime';
            }

            $rules[$fieldName] = $rule;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }
}
