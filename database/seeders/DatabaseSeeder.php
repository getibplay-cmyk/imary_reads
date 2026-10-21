<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['novels', 'روايات', 'Romans', 'Novels', 'book'], ['fantasy', 'فانتازيا', 'Fantasy', 'Fantasy', 'star'], ['history', 'تاريخ', 'Histoire', 'History', 'home'], ['literature', 'أدب', 'Littérature', 'Literature', 'pen'], ['travel', 'رحلات', 'Voyages', 'Travel', 'grid'], ['short-stories', 'قصص قصيرة', 'Nouvelles', 'Short stories', 'bookmark'], ['knowledge', 'معرفة', 'Savoirs', 'Knowledge', 'shield'], ['heritage', 'تراث', 'Patrimoine', 'Heritage', 'book'],
        ] as [$slug,$ar,$fr,$en,$icon]) {
            Category::firstOrCreate(['slug' => $slug], ['name' => compact('ar', 'fr', 'en'), 'icon' => $icon]);
        }
    }
}
