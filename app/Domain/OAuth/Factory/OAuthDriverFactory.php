<?php

namespace App\Domain\OAuth\Factory;

use App\Domain\OAuth\Models\OAuthProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\DiscordProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\TwitterProvider;

class OAuthDriverFactory
{
    /** @var array<string, class-string<AbstractProvider>> */
    private const PROVIDERS = [
        'google' => GoogleProvider::class,
        'github' => GithubProvider::class,
        'discord' => DiscordProvider::class,
        'facebook' => FacebookProvider::class,
        'x' => TwitterProvider::class,
    ];

    public function make(string $collectionId, string $provider): AbstractProvider
    {
        $class = self::PROVIDERS[$provider]
            ?? abort(422, "Unsupported provider: {$provider}");

        $config = Cache::remember(
            "oauth_config:{$collectionId}:{$provider}",
            now()->addHour(),
            fn () => OAuthProvider::query()
                ->where('collection_id', $collectionId)
                ->where('provider', $provider)
                ->enabled()
                ->firstOrFail()
        );

        return new $class(
            request: app(Request::class),
            clientId: $config->client_id,
            clientSecret: $config->client_secret,
            redirectUrl: ! empty($config->redirect_uri) ? $config->redirect_uri : route('oauth.callback'),
        );
    }
}
