<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assistant>
 */
class AssistantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'description' => fake()->sentence(),
            'avatar' => fake()->imageUrl(100, 100, 'people'),
            'capabilities' => ['chat', 'ai_response'],
            'is_active' => true
        ];
    }
}
