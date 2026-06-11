<?php

use Veloquent\Core\Support\Models\Tenant;
use Veloquent\Core\Support\Settings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $tenantName = Tenant::current() ? Tenant::current()->name : config('app.name');

        // General
        $this->migrator->add('general.app_name', $tenantName);
        $this->migrator->add('general.app_url', 'http://localhost');
        $this->migrator->add('general.locale', config('app.locale', 'en'));
        $this->migrator->add('general.contact_email', 'admin@example.com');
        $this->migrator->add('general.lock_schema_change', false);

        // Storage
        $this->migrator->add('storage.storage_driver', 'local');
        $this->migrator->add('storage.s3_key', '');
        $this->migrator->add('storage.s3_secret', '');
        $this->migrator->add('storage.s3_region', '');
        $this->migrator->add('storage.s3_bucket', '');
        $this->migrator->add('storage.s3_endpoint', '');

        // Email
        $this->migrator->add('email.mail_driver', config('mail.default') ?: 'smtp');
        $this->migrator->add('email.mail_host', config('mail.mailers.smtp.host') ?: '127.0.0.1');
        $this->migrator->add('email.mail_port', (int) (config('mail.mailers.smtp.port') ?: 1025));
        $this->migrator->add('email.mail_encryption', config('mail.mailers.smtp.encryption') ?: 'tls');
        $this->migrator->add('email.mail_username', config('mail.mailers.smtp.username') ?: '');
        $this->migrator->add('email.mail_password', config('mail.mailers.smtp.password') ?: '');
        $this->migrator->add('email.mail_from_address', config('mail.from.address') ?: 'noreply@example.com');
        $this->migrator->add('email.mail_from_name', config('mail.from.name') ?: $tenantName);
    }
};
