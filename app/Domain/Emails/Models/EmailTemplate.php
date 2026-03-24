<?php

namespace App\Domain\Emails\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['collection_id', 'action', 'content'];

    protected function casts(): array
    {
        return [];
    }
}
