<?php

namespace Database\Seeders;

use App\Models\MScanCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MScanCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoriesTitle = [
            'Outerwear', 'Footwear', 'Dress', 'Work', 'Date', 'Party'
        ];

        foreach($categoriesTitle as $title) {
            MScanCategory::create([
                'title' => $title,
                'icon' => 'pencil'
            ]);
        }
    }
}
