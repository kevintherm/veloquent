<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuthToken extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection_name',
        'collection_id',
        'record_id',
        'token_hash',
        'expires_at',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'timestamp',
            'last_used_at' => 'timestamp',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForRecord(Builder $query, string $collectionId, string $id): Builder
    {
        return $query
            ->where('collection_id', $collectionId)
            ->where('record_id', (string) $id);
    }
}
