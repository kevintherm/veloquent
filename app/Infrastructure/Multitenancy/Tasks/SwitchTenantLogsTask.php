<?php

namespace App\Infrastructure\Multitenancy\Tasks;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchTenantLogsTask implements SwitchTenantTask
{
    public function __construct(
        protected ?string $originalDailyPath = null,
        protected ?string $originalEmergencyPath = null,
        protected ?string $originalSinglePath = null,
    ) {
        $defaultLogPath = storage_path('logs/laravel.log');

        $this->originalDailyPath = $this->originalDailyPath ?? (string) config('logging.channels.daily.path', $defaultLogPath);
        $this->originalEmergencyPath = $this->originalEmergencyPath ?? (string) config('logging.channels.emergency.path', $defaultLogPath);
        $this->originalSinglePath = $this->originalSinglePath ?? (string) config('logging.channels.single.path', $defaultLogPath);
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        $tenantPathKey = $this->resolveTenantPathKey($tenant);
        $tenantLogPath = storage_path("tenants/{$tenantPathKey}/logs/laravel.log");

        File::ensureDirectoryExists(dirname($tenantLogPath));

        config()->set('logging.channels.daily.path', $tenantLogPath);
        config()->set('logging.channels.emergency.path', $tenantLogPath);
        config()->set('logging.channels.single.path', $this->originalSinglePath ?? storage_path('logs/laravel.log'));

        $this->forgetResolvedLogChannels();
    }

    public function forgetCurrent(): void
    {
        config()->set('logging.channels.daily.path', $this->originalDailyPath ?? storage_path('logs/laravel.log'));
        config()->set('logging.channels.emergency.path', $this->originalEmergencyPath ?? storage_path('logs/laravel.log'));
        config()->set('logging.channels.single.path', $this->originalSinglePath ?? storage_path('logs/laravel.log'));

        $this->forgetResolvedLogChannels();
    }

    private function forgetResolvedLogChannels(): void
    {
        if (! app()->bound('log')) {
            return;
        }

        $logManager = app('log');

        if (! $logManager instanceof LogManager) {
            return;
        }

        $logManager->forgetChannel('stack');
        $logManager->forgetChannel('daily');
        $logManager->forgetChannel('single');
        $logManager->forgetChannel('emergency');
    }

    private function resolveTenantPathKey(IsTenant $tenant): string
    {
        $tenantId = data_get($tenant, 'id');

        $tenantPathKey = trim((string) $tenantId);
        $tenantPathKey = preg_replace('/[^A-Za-z0-9_-]/', '_', $tenantPathKey) ?? '';

        if ($tenantPathKey === '') {
            throw new RuntimeException('Tenant id is required to switch log paths.');
        }

        return $tenantPathKey;
    }
}
