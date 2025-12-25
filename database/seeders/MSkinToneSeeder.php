<?php

namespace Database\Seeders;

use App\Models\MSkinTone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MSkinToneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $MSkinTones = [
            [
                'title' => 'Very Fair',
                'description' => 'Kulit sangat terang, mudah terbakar sinar matahari, jarang menggelap.'
            ],
            [
                'title' => 'Fair',
                'description' => 'Kulit terang, terkadang menggelap saat terpapar matahari.'
            ],
            [
                'title' => 'Light',
                'description' => 'Kulit cerah dengan undertone hangat atau netral.'
            ],
            [
                'title' => 'Light Medium',
                'description' => 'Kulit cerah ke medium, cukup mudah menyesuaikan berbagai warna.'
            ],
            [
                'title' => 'Medium',
                'description' => 'Kulit sedang dengan keseimbangan undertone hangat dan dingin.'
            ],
            [
                'title' => 'Tan',
                'description' => 'Kulit kecokelatan, jarang terbakar dan mudah menggelap.'
            ],
            [
                'title' => 'Deep',
                'description' => 'Kulit gelap dengan pigmen kuat dan undertone hangat.'
            ],
            [
                'title' => 'Dark',
                'description' => 'Kulit sangat gelap, kaya melanin, jarang terbakar matahari.'
            ],
        ];

        foreach ($MSkinTones as $skinTone) {
            MSkinTone::create([
                'title' => $skinTone['title'],
                'description' => $skinTone['description'],
            ]);
        }
    }
}
