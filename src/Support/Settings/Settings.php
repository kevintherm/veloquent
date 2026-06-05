<?php

namespace Veloquent\Core\Support\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;

abstract class Settings
{
    /**
     * Whether the settings have been loaded from cache/DB.
     */
    protected bool $loaded = false;

    /**
     * Stored default values of public properties.
     */
    protected array $defaults = [];

    /**
     * The names of the public properties on this settings class.
     */
    protected array $propertyNames = [];

    /**
     * Create a new settings instance (lazily initialized).
     */
    final public function __construct()
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();
            $this->propertyNames[] = $name;

            // Store the default value if it is initialized in class declaration
            if ($property->isInitialized($this)) {
                $this->defaults[$name] = $this->{$name};
            }

            // Unset the property to intercept accesses via __get/__set
            unset($this->{$name});
        }
    }

    /**
     * Intercept property retrieval to ensure settings are loaded.
     */
    public function __get(string $name)
    {
        $this->ensureLoaded();
        return $this->{$name};
    }

    /**
     * Intercept property updates.
     */
    public function __set(string $name, $value): void
    {
        $this->ensureLoaded();
        $this->{$name} = $value;
    }

    /**
     * Intercept isset checks.
     */
    public function __isset(string $name): bool
    {
        $this->ensureLoaded();
        return isset($this->{$name});
    }

    /**
     * Load settings dynamically on property access.
     */
    protected function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        $group = static::group();
        $cacheKey = static::getCacheKey();
        $values = [];
        $loadedFromCache = false;

        // 1. Try to load from cache
        if (config('settings.cache.enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                if (config('settings.cache.encrypted', true)) {
                    try {
                        $decrypted = decrypt($cached);
                        if (is_array($decrypted)) {
                            $values = $decrypted;
                            $loadedFromCache = true;
                        }
                    } catch (\Exception $e) {
                        // 
                    }
                } else {
                    if (is_array($cached)) {
                        $values = $cached;
                        $loadedFromCache = true;
                    }
                }
            }
        }

        // 2. If not cached, load from database
        if (! $loadedFromCache) {
            $records = DB::table('settings')
                ->where('group', $group)
                ->get();

            foreach ($records as $record) {
                $values[$record->name] = json_decode($record->payload, true);
            }

            // Cache the retrieved values
            if (config('settings.cache.enabled', true)) {
                // Merge database values with defaults to ensure we cache all properties
                $cacheData = array_merge($this->defaults, $values);
                if (config('settings.cache.encrypted', true)) {
                    Cache::forever($cacheKey, encrypt($cacheData));
                } else {
                    Cache::forever($cacheKey, $cacheData);
                }
            }
        }

        // 3. Fill the properties
        $casts = static::casts();
        foreach ($this->propertyNames as $name) {
            $value = $values[$name] ?? $this->defaults[$name] ?? null;

            // Apply custom cast GET if defined and loaded from DB/cache
            if (isset($values[$name]) && isset($casts[$name])) {
                $castClass = $casts[$name];
                $castInstance = new $castClass();
                if (method_exists($castInstance, 'get')) {
                    $value = $castInstance->get($value);
                }
            }

            // Strictly cast property to its declared PHP type
            $reflection = new ReflectionProperty($this, $name);
            if ($reflection->hasType()) {
                $type = $reflection->getType();
                if ($type instanceof ReflectionNamedType) {
                    $typeName = $type->getName();
                    if ($value !== null) {
                        if ($typeName === 'bool' || $typeName === 'boolean') {
                            $value = (bool) $value;
                        } elseif ($typeName === 'int' || $typeName === 'integer') {
                            $value = (int) $value;
                        } elseif ($typeName === 'float' || $typeName === 'double') {
                            $value = (float) $value;
                        } elseif ($typeName === 'string') {
                            $value = (string) $value;
                        } elseif ($typeName === 'array') {
                            $value = (array) $value;
                        }
                    }
                }
            }

            // Re-initialize the property so future accesses bypass magic methods
            $this->{$name} = $value;
        }
    }

    /**
     * Get the settings group name.
     */
    abstract public static function group(): string;

    /**
     * Define property casts.
     *
     * @return array<string, string>
     */
    public static function casts(): array
    {
        return [];
    }

    /**
     * Save the modified properties to the database and clear the cache.
     */
    public function save(): void
    {
        $this->ensureLoaded();
        $group = static::group();

        foreach ($this->propertyNames as $name) {
            $value = $this->{$name};

            // Apply custom cast SET if defined
            $casts = static::casts();
            if (isset($casts[$name])) {
                $castClass = $casts[$name];
                $castInstance = new $castClass();
                if (method_exists($castInstance, 'set')) {
                    $value = $castInstance->set($value);
                }
            }

            // Encode value to JSON for DB storage
            $payload = json_encode($value);

            DB::table('settings')->updateOrInsert(
                ['group' => $group, 'name' => $name],
                [
                    'payload' => $payload,
                    'locked' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Clear the cache for this group
        static::clearCache();

        // Also clear the general API settings cache in TenantSettingsService if registered
        if (app()->bound(\Veloquent\Core\Domain\Settings\TenantSettingsService::class)) {
            app(\Veloquent\Core\Domain\Settings\TenantSettingsService::class)->clearCache();
        }
    }

    /**
     * Load settings (lazily instantiated).
     */
    public static function load(): static
    {
        return new static();
    }

    /**
     * Clear the cache for this settings group.
     */
    public static function clearCache(): void
    {
        Cache::forget(static::getCacheKey());
    }

    /**
     * Get the cache key for this group.
     */
    protected static function getCacheKey(): string
    {
        return 'settings.' . static::group();
    }

    /**
     * Convert settings to an array.
     */
    public function toArray(): array
    {
        $this->ensureLoaded();

        $array = [];
        foreach ($this->propertyNames as $name) {
            $array[$name] = $this->{$name};
        }

        return $array;
    }
}
