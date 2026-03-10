<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $fillable = ['collection_name', 'record_id', 'token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
