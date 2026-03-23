<?php

namespace App\Domain\Realtime\Models;

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
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
        ];
    }
}
