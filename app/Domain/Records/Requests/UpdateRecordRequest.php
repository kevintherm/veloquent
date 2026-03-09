<?php

namespace App\Domain\Records\Requests;

class UpdateRecordRequest extends BaseRecordRequest
{
    public function rules(): array
    {
        return $this->getDynamicValidationRules();
    }

    public function getRecordData(): array
    {
        return $this->validated();
    }
}
