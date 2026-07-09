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
            ['email' => 'admin@local.test'],
            [
                'name' => 'Admin Utama',
                'role' => UserRole::Admin,
                'modules' => [UserRole::Admin->value, UserRole::Cogs->value, UserRole::Kasir->value],
                'password' => 'password',
            ]
        );

        User::updateOrCreate(
            ['email' => 'cogs@local.test'],
            [
                'name' => 'Admin COGS',
                'role' => UserRole::Cogs,
                'modules' => [UserRole::Cogs->value],
                'password' => 'password',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir@local.test'],
            [
                'name' => 'Kasir Demo',
                'role' => UserRole::Kasir,
                'modules' => [UserRole::Kasir->value],
                'password' => 'password',
            ]
        );
    }
}
