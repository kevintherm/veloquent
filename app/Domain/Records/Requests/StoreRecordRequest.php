<?php

namespace App\Domain\Records\Requests;

class StoreRecordRequest extends BaseRecordRequest
{
    public function rules(): array
    {
        return $this->getDynamicValidationRules();
    }

    public function getRecordData(): array
    {
        $collection = $this->route('collection');
        $data = $this->validated();

        // Filter out null auto-fill fields (created_at, updated_at, etc.)
        $data = $this->filterAutoFillFields($data, $collection);

        return $data;
    }
}
