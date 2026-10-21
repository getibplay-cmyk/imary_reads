<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'slug' => fake()->unique()->slug(), 'bio' => 'كاتب قصص قصيرة.'];
    }
}
