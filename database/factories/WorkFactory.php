<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkFactory extends Factory
{
    public function definition(): array
    {
        return ['owner_id' => User::factory()->state(['role' => 'author']), 'author_id' => Author::factory(), 'category_id' => Category::factory(), 'title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(), 'synopsis' => 'حكاية جديدة عن مدينة قديمة وأسرارها المنسية.', 'language' => 'ar', 'kind' => 'novel', 'status' => 'draft', 'completion' => 'ongoing', 'license' => 'all_rights_reserved', 'rights_confirmed' => true, 'allow_download' => true, 'allow_comments' => true];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()->subMinute()]);
    }
}
