<?php

namespace App\Domain\OAuth\Models;

use App\Domain\Collections\Models\Collection;
use Database\Factories\OAuthProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthProvider extends Model
{
    use HasFactory;

    protected static function newFactory(): OAuthProviderFactory
    {
        return OAuthProviderFactory::new();
    }

    protected $table = 'oauth_providers';

    protected $fillable = [
        'collection_id',
        'provider',
        'enabled',
        'client_id',
        'client_secret',
        'redirect_uri',
        'scopes',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'scopes' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
