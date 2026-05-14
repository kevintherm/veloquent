<?php

namespace Veloquent\Core\Infrastructure\Models;

use Veloquent\Core\Observers\TenantObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;

#[ObservedBy(TenantObserver::class)]
class Tenant extends SpatieTenant
{
    public static function findByIdCached(string $id): ?self
    {
        $ttl = config('velo.collection_cache_ttl', 0);
        $key = "velo:tenant:id:{$id}";

        $cached = Cache::get($key);
        if ($cached instanceof self) {
            return $cached;
        }

        $tenant = self::find($id);

        if ($tenant) {
            $ttl > 0
                ? Cache::put($key, $tenant, $ttl)
                : Cache::forever($key, $tenant);
        }

        return $tenant;
    }

    public static function findByDomainCached(string $domain): ?self
    {
        $ttl = config('velo.collection_cache_ttl', 0);
        $key = "velo:tenant:domain:{$domain}";

        $cached = Cache::get($key);
        if ($cached instanceof self) {
            return $cached;
        }

        $tenant = self::where('domain', $domain)->first();

        if ($tenant) {
            $ttl > 0
                ? Cache::put($key, $tenant, $ttl)
                : Cache::forever($key, $tenant);
        }

        return $tenant;
    }

    public function getDatabaseName(): string
    {
        return (string) ($this->database ?? (app()->runningUnitTests() ? ':memory:' : ''));
    }

    protected $fillable = [
        'name',
        'domain',
        'database',
    ];
}
