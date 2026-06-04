<?php

namespace Veloquent\Core\Support\Multitenancy\Tasks;

use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Settings\EmailSettings;
use Veloquent\Core\Domain\Settings\GeneralSettings;
use Veloquent\Core\Support\Settings\SettingsContainer;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class ApplyTenantSettingsTask implements SwitchTenantTask
{
    protected array $originalConfig = [];

    public function makeCurrent(IsTenant $tenant): void
    {
        $this->clearSettingsContainerInstances();

        $aiSettings = app(AiSettings::class);
        $provider = $aiSettings->ai_provider;

        $this->originalConfig = [
            'app.name' => config('app.name'),
            'app.locale' => config('app.locale'),
            'mail.default' => config('mail.default'),
            'mail.mailers.smtp.host' => config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption'),
            'mail.mailers.smtp.username' => config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => config('mail.mailers.smtp.password'),
            'mail.from.address' => config('mail.from.address'),
            'mail.from.name' => config('mail.from.name'),
            'ai.default' => config('ai.default'),
        ];

        if ($provider) {
            $this->originalConfig["ai.providers.{$provider}.driver"] = config("ai.providers.{$provider}.driver");
            $this->originalConfig["ai.providers.{$provider}.key"] = config("ai.providers.{$provider}.key");
            $this->originalConfig["ai.providers.{$provider}.model"] = config("ai.providers.{$provider}.model");
        }

        $generalSettings = app(GeneralSettings::class);
        $emailSettings = app(EmailSettings::class);

        config([
            'app.name' => $generalSettings->app_name,
            'app.locale' => $generalSettings->locale,
            'mail.default' => $emailSettings->mail_driver,
            'mail.mailers.smtp.host' => $emailSettings->mail_host,
            'mail.mailers.smtp.port' => $emailSettings->mail_port,
            'mail.mailers.smtp.encryption' => $emailSettings->mail_encryption,
            'mail.mailers.smtp.username' => $emailSettings->mail_username,
            'mail.mailers.smtp.password' => $emailSettings->mail_password,
            'mail.from.address' => $emailSettings->mail_from_address,
            'mail.from.name' => $emailSettings->mail_from_name,
        ]);

        if ($provider && $aiSettings->ai_api_key) {
            config([
                'ai.default' => $provider,
                "ai.providers.{$provider}.driver" => $provider,
                "ai.providers.{$provider}.key" => $aiSettings->ai_api_key,
                "ai.providers.{$provider}.model" => $aiSettings->ai_model,
            ]);
        }

        if (app()->bound('mailer')) {
            app()->forgetInstance('mailer');
        }
    }

    public function forgetCurrent(): void
    {
        $this->clearSettingsContainerInstances();

        config($this->originalConfig);

        if (app()->bound('mailer')) {
            app()->forgetInstance('mailer');
        }
    }

    protected function clearSettingsContainerInstances(): void
    {
        if (app()->bound(SettingsContainer::class)) {
            $classes = app(SettingsContainer::class)->getSettingClasses();
            foreach ($classes as $class) {
                app()->forgetInstance($class);
            }
        }
    }
}
