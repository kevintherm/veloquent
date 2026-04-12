<?php

namespace App\Infrastructure\Multitenancy\Tasks;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchTenantRedisPrefixTask implements SwitchTenantTask
{
    public function __construct(
        protected ?string $originalRedisPrefix = null,
        protected string $tenantPrefixBase = 'tenant_',
    ) {
        $this->originalRedisPrefix = (string) config('database.redis.options.prefix', '');
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        $tenantId = $this->resolveTenantId($tenant);
        $tenantPrefix = ($this->originalRedisPrefix ?? '').$this->tenantPrefixBase.$tenantId.'_';

        $this->setRedisPrefix($tenantPrefix);
    }

    public function forgetCurrent(): void
    {
        $this->setRedisPrefix($this->originalRedisPrefix ?? '');
    }

    private function setRedisPrefix(string $prefix): void
    {
        config()->set('database.redis.options.prefix', $prefix);

        $this->forgetResolvedRedisConnections();
    }

    private function resolveTenantId(IsTenant $tenant): string
    {
        $tenantId = trim((string) data_get($tenant, 'id', ''));

        if ($tenantId === '') {
            throw new RuntimeException('Tenant id is required to switch Redis prefix.');
        }

        return $tenantId;
    }

    private function forgetResolvedRedisConnections(): void
    {
        if (! app()->bound('redis')) {
            return;
        }

        $redis = app('redis');

        if ($redis instanceof RedisManager) {
            $configuredConnections = array_keys((array) config('database.redis', []));

            foreach ($configuredConnections as $connectionName) {
                if ($connectionName === 'client' || $connectionName === 'options') {
                    continue;
                }

                $redis->purge($connectionName);
            }
        }

        app()->forgetInstance('redis');
        Redis::clearResolvedInstances();
    }
}
