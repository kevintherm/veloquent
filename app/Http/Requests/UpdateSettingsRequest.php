<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // General
            'general' => ['required', 'array'],
            'general.app_name' => ['required', 'string', 'max:255'],
            'general.app_url' => ['required', 'url'],
            'general.timezone' => ['required', 'string'],
            'general.locale' => ['required', 'in:en'],
            'general.contact_email' => ['required', 'email'],
            'general.lock_schema_change' => ['boolean'],

            // Storage
            'storage' => ['required', 'array'],
            'storage.storage_driver' => ['required', 'in:local,s3'],
            'storage.s3_key' => ['required_if:storage.storage_driver,s3', 'nullable', 'string'],
            'storage.s3_secret' => ['required_if:storage.storage_driver,s3', 'nullable', 'string'],
            'storage.s3_region' => ['required_if:storage.storage_driver,s3', 'nullable', 'string'],
            'storage.s3_bucket' => ['required_if:storage.storage_driver,s3', 'nullable', 'string'],
            'storage.s3_endpoint' => ['nullable', 'url'],

            // Email
            'email' => ['required', 'array'],
            'email.mail_driver' => ['required', 'in:smtp,sendmail,mailgun'],
            'email.mail_host' => ['required', 'string'],
            'email.mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'email.mail_encryption' => ['required', 'in:tls,ssl'],
            'email.mail_username' => ['nullable', 'string'],
            'email.mail_password' => ['nullable', 'string'],
            'email.mail_from_address' => ['required', 'email'],
            'email.mail_from_name' => ['required', 'string', 'max:255'],
        ];
    }
}
