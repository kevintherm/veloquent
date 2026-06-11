<?php

namespace Veloquent\Core\Support\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Veloquent\Core\Observers\TenantObserver;
use Veloquent\Core\Support\Traits\HasUtcDates;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * @property string|null $database
 * @property string $name
 * @property string $domain
 * @property Carbon $created_at
 */
#[ObservedBy(TenantObserver::class)]
class Tenant extends SpatieTenant
{
    use HasUtcDates;

    /**
     * Find a tenant by its primary key.
     */
    public static function find($id, $columns = ['*'])
    {
        if (! config('velo.tenancy_enabled', true)) {
            return self::getVirtualTenant();
        }

        return static::query()->find($id, $columns);
    }

    public static function findByIdCached(string $id): ?self
    {
        if (! config('velo.tenancy_enabled', true)) {
            return self::getVirtualTenant();
        }

        $ttl = config('velo.collection_cache_ttl', 0);
        $key = "velo:tenant:id:{$id}";

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return (new self())->newFromBuilder($cached);
        }

        $tenant = self::find($id);

        if ($tenant) {
            $ttl > 0
                ? Cache::put($key, $tenant->getAttributes(), $ttl)
                : Cache::forever($key, $tenant->getAttributes());
        }

        return $tenant;
    }

    public static function findByDomainCached(string $domain): ?self
    {
        if (! config('velo.tenancy_enabled', true)) {
            return self::getVirtualTenant();
        }

        $ttl = config('velo.collection_cache_ttl', 0);
        $key = "velo:tenant:domain:{$domain}";

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return (new self())->newFromBuilder($cached);
        }

        $tenant = self::where('domain', $domain)->first();

        if ($tenant) {
            $ttl > 0
                ? Cache::put($key, $tenant->getAttributes(), $ttl)
                : Cache::forever($key, $tenant->getAttributes());
        }

        return $tenant;
    }

    public function getDatabaseName(): string
    {
        if ($this->database) {
            return (string) $this->database;
        }

        if (app()->runningUnitTests()) {
            return ':memory:';
        }

        $landlordConnection = config('multitenancy.landlord_database_connection_name') ?? config('database.default');

        return (string) config("database.connections.{$landlordConnection}.database");
    }

    public static function getVirtualTenant(): self
    {
        $tenant = new self([
            'name' => 'Default Tenant',
            'domain' => request()->getHost() ?: 'localhost',
            'database' => null,
        ]);

        $tenant->id = 1;

        return $tenant;
    }

    protected $fillable = [
        'name',
        'domain',
        'database',
    ];
}
