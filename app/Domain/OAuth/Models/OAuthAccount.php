<?php

namespace App\Domain\OAuth\Models;

use App\Domain\Collections\Models\Collection;
use Database\Factories\OAuthAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAccount extends Model
{
    use HasFactory, HasUlids;

    protected static function newFactory(): OAuthAccountFactory
    {
        return OAuthAccountFactory::new();
    }

    protected $table = 'oauth_accounts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'provider_user_id',
        'collection_id',
        'record_id',
        'email',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function scopeForProvider(Builder $query, string $provider, string $providerUserId, string $collectionId): Builder
    {
        return $query
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->where('collection_id', $collectionId);
    }
}
