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
        MScanCategory::truncate(); // hapus data lama

        $categories = [
            // Item
            ['title' => 'Outer',      'icon' => 'shirt-outline',    'type' => 'item'],
            ['title' => 'Atasan',     'icon' => 'shirt-outline',    'type' => 'item'],
            ['title' => 'Bawahan',    'icon' => 'shirt-outline',    'type' => 'item'],
            ['title' => 'Dress',      'icon' => 'dress-outline',    'type' => 'item'],
            ['title' => 'Sepatu',     'icon' => 'footprint-outline','type' => 'item'],
            ['title' => 'Aksesories', 'icon' => 'glasses-outline',  'type' => 'item'],

            // Occasion
            ['title' => 'Kerja',    'icon' => 'briefcase-outline', 'type' => 'occasion'],
            ['title' => 'Kuliah',   'icon' => 'school-outline',    'type' => 'occasion'],
            ['title' => 'Harian',   'icon' => 'sunny-outline',     'type' => 'occasion'],
            ['title' => 'Hangout',  'icon' => 'cafe-outline',      'type' => 'occasion'],
            ['title' => 'Pesta',    'icon' => 'balloon-outline',   'type' => 'occasion'],
            ['title' => 'Formal',   'icon' => 'ribbon-outline',    'type' => 'occasion'],
            ['title' => 'Olahraga', 'icon' => 'barbell-outline',   'type' => 'occasion'],
            ['title' => 'Liburan',  'icon' => 'airplane-outline',  'type' => 'occasion'],

            // Style
            ['title' => 'Minimalis',    'icon' => 'remove-outline',   'type' => 'style'],
            ['title' => 'Maskulin',     'icon' => 'male-outline',     'type' => 'style'],
            ['title' => 'Feminim',      'icon' => 'female-outline',   'type' => 'style'],
            ['title' => 'Korean style', 'icon' => 'star-outline',     'type' => 'style'],
            ['title' => 'Vintage',      'icon' => 'time-outline',     'type' => 'style'],
            ['title' => 'Elegan',       'icon' => 'diamond-outline',  'type' => 'style'],

            // Hijab
            ['title' => 'Hijab',     'icon' => 'person-outline', 'type' => 'hijab'],
            ['title' => 'Non Hijab', 'icon' => 'person-outline', 'type' => 'hijab'],
        ];

        foreach ($categories as $cat) {
            MScanCategory::create($cat);
        }
    }
}
