<?php

namespace App\Http\Requests;

use App\Domain\Settings\Resolvers\TenantStorageResolver;
use App\Domain\Settings\StorageSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('storage.storage_driver') === 's3') {
                    $config = $this->input('storage', []);
                    $currentSettings = app(StorageSettings::class);

                    $isUnchanged = $currentSettings->storage_driver === 's3'
                        && ($config['s3_key'] ?? '') === $currentSettings->s3_key
                        && ($config['s3_secret'] ?? '') === $currentSettings->s3_secret
                        && ($config['s3_region'] ?? '') === $currentSettings->s3_region
                        && ($config['s3_bucket'] ?? '') === $currentSettings->s3_bucket
                        && ($config['s3_endpoint'] ?? '') === $currentSettings->s3_endpoint;

                    if ($isUnchanged) {
                        return;
                    }

                    $resolver = app(TenantStorageResolver::class);
                    if (! $resolver->testConnection($config)) {
                        $validator->errors()->add('storage.storage_driver', 'The S3 credentials are invalid or the bucket is not writable.');
                    }
                }
            },
        ];
    }
}
