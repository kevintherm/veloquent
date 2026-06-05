<?php

namespace Veloquent\Core\Support\Http\Requests;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Settings\EmailSettings;
use Veloquent\Core\Domain\Settings\StorageSettings;
use Veloquent\Core\Domain\Settings\Resolvers\TenantStorageResolver;

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
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        // For email password:
        if ($this->has('email.mail_password') && ($this->input('email.mail_password') === '••••••••' || $this->input('email.mail_password') === null || $this->input('email.mail_password') === '')) {
            $this->merge([
                'email' => array_merge($this->input('email', []), [
                    'mail_password' => app(EmailSettings::class)->mail_password,
                ]),
            ]);
        }

        // For storage s3_secret:
        if ($this->has('storage.s3_secret') && ($this->input('storage.s3_secret') === '••••••••' || $this->input('storage.s3_secret') === null || $this->input('storage.s3_secret') === '')) {
            $this->merge([
                'storage' => array_merge($this->input('storage', []), [
                    's3_secret' => app(StorageSettings::class)->s3_secret,
                ]),
            ]);
        }

        // For AI API key:
        if ($this->has('ai.ai_api_key') && ($this->input('ai.ai_api_key') === '••••••••' || $this->input('ai.ai_api_key') === null || $this->input('ai.ai_api_key') === '')) {
            $this->merge([
                'ai' => array_merge($this->input('ai', []), [
                    'ai_api_key' => app(AiSettings::class)->ai_api_key,
                ]),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        if ($this->has('general')) {
            $rules['general'] = ['array'];
            $rules['general.app_name'] = ['required', 'string', 'max:255'];
            $rules['general.app_url'] = ['required', 'url'];
            $rules['general.locale'] = ['required', 'in:en'];
            $rules['general.contact_email'] = ['required', 'email'];
            $rules['general.lock_schema_change'] = ['boolean'];
        }

        if ($this->has('storage')) {
            $rules['storage'] = ['array'];
            $rules['storage.storage_driver'] = ['required', 'in:local,s3'];
            $rules['storage.s3_key'] = ['required_if:storage.storage_driver,s3', 'nullable', 'string'];
            $rules['storage.s3_secret'] = ['required_if:storage.storage_driver,s3', 'nullable', 'string'];
            $rules['storage.s3_region'] = ['required_if:storage.storage_driver,s3', 'nullable', 'string'];
            $rules['storage.s3_bucket'] = ['required_if:storage.storage_driver,s3', 'nullable', 'string'];
            $rules['storage.s3_endpoint'] = ['nullable', 'url'];
        }

        if ($this->has('email')) {
            $rules['email'] = ['array'];
            $rules['email.mail_driver'] = ['required', 'in:smtp,sendmail,mailgun'];
            $rules['email.mail_host'] = ['required', 'string'];
            $rules['email.mail_port'] = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['email.mail_encryption'] = ['required', 'in:tls,ssl'];
            $rules['email.mail_username'] = ['nullable', 'string'];
            $rules['email.mail_password'] = ['nullable', 'string'];
            $rules['email.mail_from_address'] = ['required', 'email'];
            $rules['email.mail_from_name'] = ['required', 'string', 'max:255'];
        }

        if ($this->has('ai')) {
            $rules['ai'] = ['array'];
            $rules['ai.ai_provider'] = ['nullable', 'string', 'in:openai,gemini,anthropic,deepseek,groq,openrouter,mistral,xai'];
            $rules['ai.ai_model'] = ['nullable', 'string', 'max:255'];
            $rules['ai.ai_api_key'] = ['nullable', 'string'];
        }

        return $rules;
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

                    if (! $isUnchanged) {
                        $resolver = app(TenantStorageResolver::class);
                        if (! $resolver->testConnection($config)) {
                            $validator->errors()->add('storage.storage_driver', 'The S3 credentials are invalid or the bucket is not writable.');
                        }
                    }
                }

                // Email Validation
                $emailConfig = $this->input('email', []);
                $currentEmailSettings = app(EmailSettings::class);

                $isEmailUnchanged =
                    ($emailConfig['mail_driver'] ?? '') === $currentEmailSettings->mail_driver
                    && ($emailConfig['mail_host'] ?? '') === $currentEmailSettings->mail_host
                    && (int) ($emailConfig['mail_port'] ?? 0) === $currentEmailSettings->mail_port
                    && ($emailConfig['mail_encryption'] ?? '') === $currentEmailSettings->mail_encryption
                    && ($emailConfig['mail_username'] ?? '') === $currentEmailSettings->mail_username
                    && ($emailConfig['mail_password'] ?? '') === $currentEmailSettings->mail_password
                    && ($emailConfig['mail_from_address'] ?? '') === $currentEmailSettings->mail_from_address
                    && ($emailConfig['mail_from_name'] ?? '') === $currentEmailSettings->mail_from_name;

                if (! $isEmailUnchanged && ! empty($emailConfig)) {
                    $testMailer = '__test_email_connection_'.uniqid();
                    try {
                        $mailerConfig = [
                            'transport' => $emailConfig['mail_driver'] ?? 'smtp',
                            'host' => $emailConfig['mail_host'] ?? '127.0.0.1',
                            'port' => $emailConfig['mail_port'] ?? 1025,
                            'encryption' => $emailConfig['mail_encryption'] ?? 'tls',
                            'username' => $emailConfig['mail_username'] ?? null,
                            'password' => $emailConfig['mail_password'] ?? null,
                            'timeout' => 5,
                        ];

                        config(["mail.mailers.{$testMailer}" => $mailerConfig]);

                        Mail::mailer($testMailer)->raw('Veloquent email connection test.', function ($message) use ($emailConfig) {
                            $message->to($emailConfig['mail_from_address'])
                                ->from($emailConfig['mail_from_address'], $emailConfig['mail_from_name'])
                                ->subject('Veloquent Email Test');
                        });
                    } catch (\Exception $e) {
                        Log::warning('Tenant email test failed: '.$e->getMessage());
                        $validator->errors()->add('email.mail_driver', 'Failed to send test email: '.$e->getMessage());
                    } finally {
                        config(["mail.mailers.{$testMailer}" => null]);
                    }
                }

                // AI Validation
                if ($this->has('ai')) {
                    $aiConfig = $this->input('ai', []);
                    $currentAiSettings = app(AiSettings::class);

                    $isAiUnchanged =
                        ($aiConfig['ai_provider'] ?? '') === $currentAiSettings->ai_provider
                        && ($aiConfig['ai_model'] ?? '') === $currentAiSettings->ai_model
                        && ($aiConfig['ai_api_key'] ?? '') === $currentAiSettings->ai_api_key;

                    if (! $isAiUnchanged && ! empty($aiConfig['ai_provider']) && ! empty($aiConfig['ai_api_key'])) {
                        $provider = $aiConfig['ai_provider'];
                        $model = $aiConfig['ai_model'] ?? 'gpt-4o-mini';
                        $apiKey = $aiConfig['ai_api_key'];

                        config([
                            'ai.default' => $provider,
                            "ai.providers.{$provider}.driver" => $provider,
                            "ai.providers.{$provider}.key" => $apiKey,
                            "ai.providers.{$provider}.model" => $model,
                        ]);

                        try {
                            $agent = new \Veloquent\Core\Domain\Ai\Agents\VeloquentAgent(
                                instructions: 'Verify connection.',
                                messages: [],
                                temperature: 0.7
                            );
                            $agent->prompt(
                                prompt: 'Hello. Respond with the single word "OK" to confirm connection.',
                                provider: $provider,
                                model: $model
                            );
                        } catch (\Exception $e) {
                            $errorMessage = $e->getMessage();
                            
                            $requestException = null;
                            if ($e instanceof \Illuminate\Http\Client\RequestException) {
                                $requestException = $e;
                            } elseif ($e->getPrevious() instanceof \Illuminate\Http\Client\RequestException) {
                                $requestException = $e->getPrevious();
                            }

                            if ($requestException && $requestException->response !== null) {
                                $res = $requestException->response;
                                $bodyMessage = null;
                                if (is_array($json = $res->json())) {
                                    $bodyMessage = $res->json('error.message') 
                                        ?: $res->json('message') 
                                        ?: (is_string($res->json('error')) ? $res->json('error') : null);
                                }
                                if (!empty($bodyMessage)) {
                                    $errorMessage = $bodyMessage;
                                }
                            }

                            $validator->errors()->add('ai.ai_provider', 'AI connection test failed: ' . $errorMessage);
                        }
                    }
                }
            },
        ];
    }
}
