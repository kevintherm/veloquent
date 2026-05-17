<?php

namespace Veloquent\Core\Domain\Emails\Models;

use Illuminate\Database\Eloquent\Model;
use Veloquent\Core\Support\Traits\HasUtcDates;

class EmailTemplate extends Model
{
    use HasUtcDates;

    protected $fillable = ['collection_id', 'action', 'content'];

    protected function casts(): array
    {
        return [];
    }
}
