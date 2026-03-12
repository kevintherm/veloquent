<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $fillable = ['record_id', 'token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
