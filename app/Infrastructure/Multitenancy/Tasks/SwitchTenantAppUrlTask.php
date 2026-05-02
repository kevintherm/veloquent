<?php

namespace App\Infrastructure\Multitenancy\Tasks;

use Illuminate\Support\Facades\URL;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchTenantAppUrlTask implements SwitchTenantTask
{
    public function __construct(
        protected ?string $originalAppUrl = null,
    ) {
        $configuredAppUrl = (string) config('app.url', 'http://localhost');

        $this->originalAppUrl = $this->normalizeBaseUrl($this->originalAppUrl ?? $configuredAppUrl);
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        $tenantDomain = $this->resolveTenantDomain($tenant);
        $tenantAppUrl = $this->replaceHostInUrl($this->originalAppUrl ?? 'http://localhost', $tenantDomain);

        $this->applyAppUrl($tenantAppUrl);
    }

    public function forgetCurrent(): void
    {
        $this->applyAppUrl($this->originalAppUrl ?? 'http://localhost');
    }

    private function applyAppUrl(string $appUrl): void
    {
        config()->set('app.url', $appUrl);
        URL::forceRootUrl($appUrl);
    }

    private function resolveTenantDomain(IsTenant $tenant): string
    {
        $tenantDomain = trim((string) data_get($tenant, 'domain', ''));

        if ($tenantDomain === '') {
            if (app()->runningUnitTests()) {
                return 'localhost';
            }

            throw new RuntimeException('Tenant domain is required to switch app URL.');
        }

        $host = parse_url(
            str_contains($tenantDomain, '://') ? $tenantDomain : "http://{$tenantDomain}",
            PHP_URL_HOST,
        );

        if (! is_string($host) || $host === '') {
            throw new RuntimeException("Tenant domain [{$tenantDomain}] is invalid.");
        }

        return strtolower($host);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $parsedUrl = parse_url($baseUrl);

        if ($parsedUrl === false || ! isset($parsedUrl['host'])) {
            return 'http://localhost';
        }

        return $this->replaceHostInUrl($baseUrl, (string) $parsedUrl['host']);
    }

    private function replaceHostInUrl(string $baseUrl, string $host): string
    {
        $parsedUrl = parse_url($baseUrl);

        if ($parsedUrl === false) {
            return "http://{$host}";
        }

        $scheme = (string) ($parsedUrl['scheme'] ?? 'http');
        $port = isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '';
        $path = (string) ($parsedUrl['path'] ?? '');
        $query = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#'.$parsedUrl['fragment'] : '';
        $user = (string) ($parsedUrl['user'] ?? '');
        $pass = isset($parsedUrl['pass']) ? ':'.$parsedUrl['pass'] : '';
        $auth = $user !== '' ? $user.$pass.'@' : '';

        return rtrim("{$scheme}://{$auth}{$host}{$port}{$path}{$query}{$fragment}", '/');
    }
}
