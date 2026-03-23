<?php

namespace App\Domain\Otp\Models;

use App\Domain\Otp\Enums\OtpAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OtpToken extends Model
{
    protected $fillable = [
        'collection_id',
        'record_id',
        'token_hash',
        'action',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'action' => OtpAction::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    public function scopeForRecord(Builder $query, string $collectionId, string $id): Builder
    {
        return $query
            ->where('collection_id', $collectionId)
            ->where('record_id', $id);
    }
}
