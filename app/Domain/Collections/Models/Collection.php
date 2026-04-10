<?php

namespace App\Domain\Collections\Models;

use App\Domain\Collections\Casts\FieldCollectionCast;
use App\Domain\Collections\Casts\IndexCollectionCast;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Observers\CollectionObserver;
use App\Domain\Collections\QueryBuilder\CollectionBuilder;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[ObservedBy(CollectionObserver::class)]
#[UseEloquentBuilder(CollectionBuilder::class)]
class Collection extends Model
{
    use HasFactory, HasUlids;

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    protected $fillable = [
        'type',
        'name',
        'table_name',
        'description',
        'fields',
        'api_rules',
        'indexes',
        'options',
        'is_system',
        'schema_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'fields' => FieldCollectionCast::class,
            'indexes' => IndexCollectionCast::class,
            'api_rules' => 'array',
            'options' => 'array',
            'is_system' => 'boolean',
            'schema_updated_at' => 'datetime',
        ];
    }

    public static function findByIdCached(string $id): ?self
    {
        $ttl = config('velo.collection_cache_ttl');
        $key = "velo:collection:id:{$id}";

        $callback = fn () => self::find($id);

        return $ttl > 0
            ? Cache::remember($key, $ttl, $callback)
            : Cache::rememberForever($key, $callback);
    }

    public static function findByNameCached(string $name): ?self
    {
        $ttl = config('velo.collection_cache_ttl');
        $key = "velo:collection:name:{$name}";

        $callback = fn () => self::where('name', $name)->first();

        return $ttl > 0
            ? Cache::remember($key, $ttl, $callback)
            : Cache::rememberForever($key, $callback);
    }

    public function clearCache(): void
    {
        Cache::forget("velo:collection:id:{$this->id}");
        Cache::forget("velo:collection:name:{$this->name}");
        Cache::forget("velo:collection:casts:{$this->id}");

        if ($this->wasChanged('name')) {
            Cache::forget("velo:collection:name:{$this->getOriginal('name')}");
        }
    }

    public function getCachedCasts(): array
    {
        $ttl = config('velo.collection_cache_ttl');
        $key = "velo:collection:casts:{$this->id}";

        $callback = function () {
            $casts = [];
            foreach ($this->fields ?? [] as $field) {
                $fieldName = $field['name'];

                if ($fieldName == 'password' && $this->type === CollectionType::Auth) {
                    $casts[$fieldName] = 'hashed';

                    continue;
                }

                $cast = CollectionFieldType::tryFrom($field['type'])?->eloquentCast();
                if ($cast !== null) {
                    $casts[$fieldName] = $cast;
                }
            }

            return $casts;
        };

        return $ttl > 0
            ? Cache::remember($key, $ttl, $callback)
            : Cache::rememberForever($key, $callback);
    }

    /**
     * Get the physical database table name for this collection.
     */
    public function getPhysicalTableName(): string
    {
        return $this->table_name ?? self::formatTableName($this->name, $this->is_system);
    }

    /**
     * @deprecated Use SchemaChangePlan::generateTableName($collectionName, $isSystem) instead for new collections.
     */
    public static function formatTableName(string $collectionName, ?bool $isSystem = false): string
    {
        if ($isSystem) {
            return $collectionName;
        }

        $prefix = config('velo.collection_prefix', '_velo_');

        return $prefix.$collectionName;
    }
}
