<?php

namespace App\Domain\Settings\Resolvers;

use App\Domain\Settings\StorageSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TenantStorageResolver
{
    public function __construct(protected StorageSettings $settings) {}

    public function hasOwnS3(): bool
    {
        return $this->settings->storage_driver === 's3'
            && ! empty($this->settings->s3_key)
            && ! empty($this->settings->s3_secret)
            && ! empty($this->settings->s3_bucket);
    }

    public function getS3Config(): array
    {
        return [
            'driver' => 's3',
            'key' => $this->settings->s3_key,
            'secret' => $this->settings->s3_secret,
            'region' => $this->settings->s3_region,
            'bucket' => $this->settings->s3_bucket,
            'endpoint' => $this->settings->s3_endpoint ?: null,
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
        ];
    }

    public function testConnection(array $config): bool
    {
        $diskName = '__test_s3_connection_'.uniqid();
        try {
            config(["filesystems.disks.{$diskName}" => [
                'driver' => 's3',
                'key' => $config['s3_key'] ?? '',
                'secret' => $config['s3_secret'] ?? '',
                'region' => $config['s3_region'] ?? '',
                'bucket' => $config['s3_bucket'] ?? '',
                'endpoint' => $config['s3_endpoint'] ?? null,
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                'throw' => true,
            ]]);

            $disk = Storage::disk($diskName);
            $testFile = 'test-connection.txt';
            $testContent = 'Veloquent S3 Test';

            $disk->put($testFile, $testContent);
            $retrievedContent = $disk->get($testFile);

            if ($retrievedContent !== $testContent) {
                return false;
            }

            $disk->delete($testFile);

            return true;
        } catch (\Exception $e) {
            Log::warning('Tenant S3 storage test failed: '.$e->getMessage());

            return false;
        } finally {
            config(["filesystems.disks.{$diskName}" => null]);
            if (app()->bound('filesystem')) {
                app('filesystem')->forgetDisk($diskName);
            }
        }
    }
}
