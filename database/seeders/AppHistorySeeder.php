<?php

namespace Database\Seeders;

use App\Models\AppHistory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $AppHistories = [
            'title' => 'Version 1.0.0',
            'description' => 'Initial release of Stylo application with core features and functionalities.',
            'apk_url' => 'https://google.com',
            'ios_url' => 'https://google.com',
        ];

        AppHistory::create($AppHistories);
    }
}
