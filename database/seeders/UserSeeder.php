<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['admin', 'user'];
        foreach ($roles as $role) {
            Role::create([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        $users = [
            [
                'name' => 'Admin Stylo',
                'email' => 'styloartificial@gmail.com',
                'password' => bcrypt('Stylo#0809')
            ],
            [
                'name' => 'Tiara Yoga',
                'email' => 'tiarayg@gmail.com',
                'password' => bcrypt('Tiara@123')
            ]
        ];

        foreach($users as $user) {
            User::create($user)->assignRole($user['name'] == 'Admin Stylo' ? 'admin' : 'user');
        }
    }
}
