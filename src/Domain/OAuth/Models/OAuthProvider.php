<?php

namespace Veloquent\Core\Domain\OAuth\Models;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Database\Factories\OAuthProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Veloquent\Core\Support\Traits\HasUtcDates;

/**
 * @property string $collection_id
 * @property string $provider
 * @property bool $enabled
 * @property string $client_id
 * @property string $client_secret
 * @property string $redirect_uri
 * @property array $scopes
 */
class OAuthProvider extends Model
{
    use HasFactory, HasUtcDates;

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
