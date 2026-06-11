<?php

namespace Veloquent\Core\Domain\Auth\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Veloquent\Core\Support\Traits\HasUtcDates;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @property string $collection_id
 * @property string $record_id
 * @property Carbon $expires_at
 * @property string $token_hash
 */
class AuthToken extends Model
{
    use HasUlids, HasUtcDates;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection_name',
        'collection_id',
        'record_id',
        'token_hash',
        'ip_address',
        'user_agent',
        'fingerprint',
        'revoked_at',
        'expires_at',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('expires_at', '>', now()->toDateTimeString())
            ->whereNull('revoked_at');
    }

    public function scopeForRecord(Builder $query, string $collectionId, string $id): Builder
    {
        return $query
            ->where('collection_id', $collectionId)
            ->where('record_id', (string) $id);
    }
}
