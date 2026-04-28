<?php

namespace App\Infrastructure\Multitenancy\Tasks;

use App\Domain\Settings\EmailSettings;
use App\Domain\Settings\GeneralSettings;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class ApplyTenantSettingsTask implements SwitchTenantTask
{
    protected array $originalConfig = [];

    public function makeCurrent(IsTenant $tenant): void
    {
        $this->originalConfig = [
            'app.name' => config('app.name'),
            'app.timezone' => config('app.timezone'),
            'app.locale' => config('app.locale'),
            'mail.default' => config('mail.default'),
            'mail.mailers.smtp.host' => config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption'),
            'mail.mailers.smtp.username' => config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => config('mail.mailers.smtp.password'),
            'mail.from.address' => config('mail.from.address'),
            'mail.from.name' => config('mail.from.name'),
        ];

        $generalSettings = app(GeneralSettings::class);
        $emailSettings = app(EmailSettings::class);

        config([
            'app.name' => $generalSettings->app_name,
            'app.timezone' => $generalSettings->timezone,
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

        if (app()->bound('mailer')) {
            app()->forgetInstance('mailer');
        }
    }

    public function forgetCurrent(): void
    {
        config($this->originalConfig);

        if (app()->bound('mailer')) {
            app()->forgetInstance('mailer');
        }
    }
}
