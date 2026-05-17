<?php

use Veloquent\Core\Support\Database\SchemaCache;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('caches hasTable result only when table exists', function () {
    Cache::flush();

    // 1. Check a non-existent table: should return false and NOT cache it
    $nonExistentTable = 'random_table_12345';
    expect(SchemaCache::hasTable($nonExistentTable))->toBeFalse();
    expect(Cache::has("velo:table_exists:{$nonExistentTable}"))->toBeFalse();

    // 2. Check an existing table: should return true and CACHE it
    $existingTable = 'collections';
    expect(SchemaCache::hasTable($existingTable))->toBeTrue();
    expect(Cache::has("velo:table_exists:{$existingTable}"))->toBeTrue();
    expect(Cache::get("velo:table_exists:{$existingTable}"))->toBeTrue();
});
