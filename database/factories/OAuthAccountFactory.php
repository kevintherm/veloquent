<?php

namespace Database\Factories;

use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Models\OAuthAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthAccount>
 */
class OAuthAccountFactory extends Factory
{
    protected $model = OAuthAccount::class;

    public function definition(): array
    {
        return [
            'provider' => $this->faker->randomElement(['google', 'github', 'discord', 'facebook', 'x']),
            'provider_user_id' => (string) $this->faker->unique()->randomNumber(8),
            'collection_id' => Collection::factory(),
            'record_id' => (string) Str::ulid(),
            'email' => $this->faker->safeEmail(),
        ];
    }
}
