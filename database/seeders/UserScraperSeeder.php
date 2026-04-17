<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserScraperSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'Scraper', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => 'stylo.scraper@gmail.com'],
            [
                'name'     => 'Stylo Scraper',
                'password' => Hash::make('scraper_secret_password'),
            ]
        );

        $user->assignRole($role);
    }
}