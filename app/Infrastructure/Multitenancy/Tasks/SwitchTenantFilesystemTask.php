<?php

namespace App\Infrastructure\Multitenancy\Tasks;

use App\Domain\Settings\Resolvers\TenantStorageResolver;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchTenantFilesystemTask implements SwitchTenantTask
{
    public function __construct(
        protected ?string $originalDefaultDisk = null,
        protected ?string $originalTenantDiskRoot = null,
        protected ?string $originalTenantDiskDriver = null,
        protected ?string $originalTenantDiskPrefix = null,
        protected ?string $originalRealtimeBusPath = null,
    ) {
        $tenantDiskConfig = (array) config('filesystems.disks.tenant', []);

        $this->originalDefaultDisk = $this->originalDefaultDisk ?? (string) config('filesystems.default', 'local');
        $this->originalTenantDiskRoot = $this->originalTenantDiskRoot ?? (string) config('filesystems.disks.tenant.root', storage_path('tenants/landlord/app'));
        $this->originalTenantDiskDriver = $this->originalTenantDiskDriver ?? (string) ($tenantDiskConfig['driver'] ?? 'local');
        $this->originalTenantDiskPrefix = $this->originalTenantDiskPrefix ?? (string) ($tenantDiskConfig['prefix'] ?? '');
        $this->originalRealtimeBusPath = $this->originalRealtimeBusPath ?? (string) config('velo.realtime.filesystem_bus_path', storage_path('realtime/bus'));
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        $tenantPathKey = $this->resolveTenantPathKey($tenant);
        $disks = (array) config('filesystems.disks', []);
        $originalDiskConfig = (array) ($disks[$this->originalDefaultDisk ?? ''] ?? []);
        $originalDiskDriver = is_array($originalDiskConfig)
            ? (string) ($originalDiskConfig['driver'] ?? 'local')
            : 'local';
        $tenantRootPath = storage_path("tenants/{$tenantPathKey}");
        $tenantAppPath = "{$tenantRootPath}/app";
        $tenantRealtimeBusPath = "{$tenantRootPath}/realtime/bus";
        $tenantLogsPath = "{$tenantRootPath}/logs";

        File::ensureDirectoryExists($tenantAppPath);
        File::ensureDirectoryExists($tenantRealtimeBusPath);
        File::ensureDirectoryExists($tenantLogsPath);

        config()->set('filesystems.default', 'tenant');

        $tenantStorageResolver = app(TenantStorageResolver::class);

        if ($tenantStorageResolver->hasOwnS3()) {
            config()->set('filesystems.disks.tenant', $tenantStorageResolver->getS3Config());
        } elseif ($originalDiskDriver === 's3') {
            $tenantS3Config = is_array($originalDiskConfig) ? $originalDiskConfig : [];
            $tenantS3Config['driver'] = 's3';
            $tenantS3Config['prefix'] = "tenants/{$tenantPathKey}/app";

            config()->set('filesystems.disks.tenant', $tenantS3Config);
        } else {
            config()->set('filesystems.disks.tenant.driver', 'local');
            config()->set('filesystems.disks.tenant.root', $tenantAppPath);
            config()->set('filesystems.disks.tenant.prefix', null);
        }

        config()->set('velo.realtime.filesystem_bus_path', $tenantRealtimeBusPath);

        $this->forgetResolvedDisks();
    }

    public function forgetCurrent(): void
    {
        config()->set('filesystems.default', $this->originalDefaultDisk ?? 'local');
        config()->set('filesystems.disks.tenant.root', $this->originalTenantDiskRoot ?? storage_path('tenants/landlord/app'));
        config()->set('filesystems.disks.tenant.driver', $this->originalTenantDiskDriver ?? 'local');
        config()->set('filesystems.disks.tenant.prefix', $this->originalTenantDiskPrefix !== '' ? $this->originalTenantDiskPrefix : null);
        config()->set('velo.realtime.filesystem_bus_path', $this->originalRealtimeBusPath ?? storage_path('realtime/bus'));

        $this->forgetResolvedDisks();
    }

    private function resolveTenantPathKey(IsTenant $tenant): string
    {
        $tenantId = data_get($tenant, 'id');

        $tenantPathKey = trim((string) $tenantId);
        $tenantPathKey = preg_replace('/[^A-Za-z0-9_-]/', '_', $tenantPathKey) ?? '';

        if ($tenantPathKey === '') {
            throw new RuntimeException('Tenant id is required to switch filesystem paths.');
        }

        return $tenantPathKey;
    }

    private function forgetResolvedDisks(): void
    {
        if (! app()->bound('filesystem')) {
            return;
        }

        $filesystemManager = app('filesystem');

        if (! $filesystemManager instanceof FilesystemManager || ! method_exists($filesystemManager, 'forgetDisk')) {
            return;
        }

        $filesystemManager->forgetDisk('tenant');

        if (is_string($this->originalDefaultDisk) && $this->originalDefaultDisk !== '') {
            $filesystemManager->forgetDisk($this->originalDefaultDisk);
        }
    }
}
