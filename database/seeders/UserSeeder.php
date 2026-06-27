<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'cogs@local.test'],
            [
                'name' => 'Admin COGS',
                'role' => UserRole::Cogs,
                'password' => 'password',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir@local.test'],
            [
                'name' => 'Kasir Demo',
                'role' => UserRole::Kasir,
                'password' => 'password',
            ]
        );
    }
}
