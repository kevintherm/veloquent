<?php

namespace App\Domain\Settings;

use Illuminate\Support\Facades\Cache;

class TenantSettingsService
{
    protected array $settings;

    public function __construct(
        GeneralSettings $generalSettings,
        StorageSettings $storageSettings,
        EmailSettings $emailSettings
    ) {
        $this->settings = [
            'general' => $generalSettings,
            'storage' => $storageSettings,
            'email' => $emailSettings,
        ];
    }

    public function get(): array
    {
        return Cache::rememberForever($this->getCacheKey(), function () {
            return collect($this->settings)
                ->map(fn ($settings) => $settings->toArray())
                ->all();
        });
    }

    public function update(array $payload): void
    {
        foreach ($this->settings as $group => $settings) {
            if (isset($payload[$group])) {
                $this->fill($settings, $payload[$group]);
                $settings->save();
            }
        }

        $this->clearCache();
    }

    protected function fill($settings, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->{$key} = $value;
            }
        }
    }

    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    protected function getCacheKey(): string
    {
        return 'tenant_api_settings';
    }
}
