<?php

namespace App\Domain\Records\Models;

use App\Domain\Collections\Models\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $timestamps = true;

    /**
     * Create a new Record instance for a specific collection
     */
    public static function forCollection(Collection $collection): self
    {
        $instance = new self;
        $instance->setTable($collection->getPhysicalTableName());

        // Set casts based on collection fields
        $casts = [];
        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];

            match ($field['type']) {
                'boolean' => $casts[$fieldName] = 'boolean',
                'integer' => $casts[$fieldName] = 'integer',
                'float', 'double' => $casts[$fieldName] = 'float',
                'date' => $casts[$fieldName] = 'date',
                'datetime' => $casts[$fieldName] = 'datetime',
                'json', 'array' => $casts[$fieldName] = 'json',
                default => null
            };
        }

        $instance->casts = $casts;

        return $instance;
    }

    /**
     * Get the table name for this record instance
     */
    public function getTable(): string
    {
        return $this->table ?? parent::getTable();
    }
}
