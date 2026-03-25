<?php

namespace Database\Factories;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Collection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'type' => CollectionType::Base,
            'description' => $this->faker->sentence(),
            'fields' => [],
            'indexes' => [],
            'is_system' => false,
        ];
    }

    public function auth(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CollectionType::Auth,
        ]);
    }
}
