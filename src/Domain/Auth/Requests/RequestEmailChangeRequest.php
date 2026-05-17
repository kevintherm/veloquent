<?php

namespace Veloquent\Core\Domain\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_email' => ['required', 'string', 'email'],
        ];
    }
}
