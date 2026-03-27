<?php

namespace App\Domain\Collections\Models;

use App\Domain\Collections\Casts\FieldCollectionCast;
use App\Domain\Collections\Casts\IndexCollectionCast;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Observers\CollectionObserver;
use App\Domain\Collections\QueryBuilder\CollectionBuilder;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CollectionObserver::class)]
#[UseEloquentBuilder(CollectionBuilder::class)]
class Collection extends Model
{
    use HasFactory, HasUlids;

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    protected $fillable = ['type', 'name', 'table_name', 'description', 'fields', 'api_rules', 'indexes', 'options', 'is_system', 'schema_updated_at'];

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
