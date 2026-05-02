<?php

namespace Veloquent\Core\Domain\SchemaManagement\Models;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaJob extends Model
{
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
