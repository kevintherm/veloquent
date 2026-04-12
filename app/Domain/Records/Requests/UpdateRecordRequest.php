<?php

namespace App\Domain\Records\Requests;

use App\Domain\Collections\Enums\CollectionType;
use Illuminate\Support\Arr;

class UpdateRecordRequest extends BaseRecordRequest
{
    public function rules(): array
    {
        $collection = $this->route('collection');

        return $this->getDynamicValidationRules(
            intervene: function ($fieldName, &$fieldRules) use ($collection) {
                if ($collection->type === CollectionType::Auth
                    && in_array($fieldName, ['email', 'password'], true)
                    && ! in_array('nullable', $fieldRules, true)) {
                    $fieldRules[] = 'nullable';
                }

                $fieldRules = Arr::map($fieldRules, function ($value) {
                    if ($value === 'required') {
                        return 'sometimes';
                    }

                    return $value;
                });
            }
        );
    }

    public function getRecordData(): array
    {
        $collection = $this->route('collection');
        $data = $this->validated();

        // Filter out null password fields for auth collections
        $data = $this->filterPasswordField($data, $collection);

        // Filter out null auto-fill fields (created_at, updated_at, etc.)
        $data = $this->filterAutoFillFields($data, $collection);

        $data = $this->normalizeRelationFieldsForWrite($data, $collection);

        return $data;
    }

    public function getRecordId(): string
    {
        return $this->route('record');
    }
}
