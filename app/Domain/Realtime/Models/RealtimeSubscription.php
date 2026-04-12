<?php

namespace App\Domain\Realtime\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class RealtimeSubscription extends Model
{
    use HasUlids;
    use UsesLandlordConnection;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
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
            'tenant_id' => 'integer',
            'expired_at' => 'datetime',
        ];
    }
}
