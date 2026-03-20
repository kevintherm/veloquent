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
                if ($fieldName === 'password' && $collection->type === CollectionType::Auth) {
                    $fieldRules = Arr::map($fieldRules, fn ($value) => $value === 'required' ? 'nullable' : $value);
                }
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
        
        return $data;
    }

    public function getRecordId(): string
    {
        return $this->route('record');
    }
}
