<?php

namespace Veloquent\Core\Domain\Realtime\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;
use Veloquent\Core\Support\Traits\HasUtcDates;

/**
 * @property string $id
 * @property int $tenant_id
 * @property string $collection_id
 * @property string $auth_collection
 * @property string $subscriber_id
 * @property string $channel
 * @property string|array|null $filter
 * @property \Carbon\Carbon $expired_at
 */
class RealtimeSubscription extends Model
{
    use HasUlids, HasUtcDates;
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
