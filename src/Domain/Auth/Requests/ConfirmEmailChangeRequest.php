<?php

namespace Veloquent\Core\Domain\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'new_email' => ['required', 'string', 'email'],
        ];
    }
}
