<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Queue>
 */
class QueueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'scheduled_date' => $this->faker->optional()->date(),
            'is_active' => $this->faker->boolean(80),
            'is_paused' => false,
            'start_time' => $this->faker->time('H:i'),
        ];
    }
}
