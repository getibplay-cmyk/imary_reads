<?php

namespace Database\Factories;

use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChapterFactory extends Factory
{
    public function definition(): array
    {
        return ['work_id' => Work::factory(), 'title' => 'الباب القديم', 'position' => 1, 'content' => '<p>كان الصباح جميلاً في المدينة القديمة.</p>', 'plain_text' => 'كان الصباح جميلاً في المدينة القديمة.', 'word_count' => 7, 'revision' => 1, 'status' => 'draft'];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()->subMinute()]);
    }
}
