<?php

use App\Infrastructure\Models\Tenant;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $tenantName = Tenant::current() ? Tenant::current()->name : config('app.name');

        // General
        $this->migrator->add('general.app_name', $tenantName);
        $this->migrator->add('general.app_url', 'http://localhost');
        $this->migrator->add('general.timezone', config('app.timezone', 'UTC'));
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
        $this->migrator->add('email.mail_driver', 'smtp');
        $this->migrator->add('email.mail_host', '127.0.0.1');
        $this->migrator->add('email.mail_port', 1025);
        $this->migrator->add('email.mail_encryption', 'tls');
        $this->migrator->add('email.mail_username', '');
        $this->migrator->add('email.mail_password', '');
        $this->migrator->add('email.mail_from_address', 'noreply@example.com');
        $this->migrator->add('email.mail_from_name', $tenantName);
    }
};
