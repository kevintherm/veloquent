<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RealtimeSubscription extends Model
{
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'collection_id',
        'auth_collection',
        'subscriber_id',
        'channel',
        'filter',
    ];
}
