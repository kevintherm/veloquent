<?php

namespace Veloquent\Core\Domain\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmEmailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
