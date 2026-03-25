<?php

namespace Database\Factories;

use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Models\OAuthProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthProvider>
 */
class OAuthProviderFactory extends Factory
{
    protected $model = OAuthProvider::class;

    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'provider' => $this->faker->randomElement(['google', 'github', 'discord', 'facebook', 'x']),
            'enabled' => true,
            'client_id' => $this->faker->uuid(),
            'client_secret' => $this->faker->password(),
            'redirect_uri' => $this->faker->url(),
            'scopes' => ['read', 'user'],
        ];
    }
}
