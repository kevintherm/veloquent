<?php

namespace Veloquent\Core\Domain\SchemaManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Veloquent\Core\Support\Traits\HasUtcDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;

/**
 * @property string $collection_id
 * @property SchemaOperation $operation
 * @property string $table_name
 * @property int|null $started_at
 */
class SchemaJob extends Model
{
    use HasUtcDates;

    protected $fillable = [
        'collection_id',
        'operation',
        'table_name',
        'started_at',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'timestamp',
            'operation' => SchemaOperation::class,
        ];
    }
}
