<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class CreateRootUser extends Command
{
    protected $signature = 'user:root
        {email? : Email akun root}
        {--name= : Nama akun root}
        {--password= : Password akun root}';

    protected $description = 'Membuat atau menjadikan sebuah akun sebagai root (akses semua modul, pilih modul saat login).';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email akun root');
        $name = $this->option('name') ?: $this->ask('Nama akun root', 'Root');
        $password = $this->option('password') ?: $this->secret('Password (kosongkan jika akun sudah ada & tidak ingin diubah)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if (! $existing && ! $password) {
            $this->error('Akun baru wajib memiliki password.');

            return self::FAILURE;
        }

        if ($password) {
            $passwordValidator = Validator::make(
                ['password' => $password],
                ['password' => ['required', 'string', Password::min(8)]]
            );

            if ($passwordValidator->fails()) {
                foreach ($passwordValidator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
        }

        $attributes = [
            'name' => $name,
            'role' => UserRole::Admin,
            'modules' => array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
            'is_root' => true,
            'must_change_password' => false,
        ];

        if ($password) {
            $attributes['password'] = Hash::make($password);
        }

        $user = User::updateOrCreate(['email' => $email], $attributes);

        $this->info(sprintf(
            'Akun root %s: %s (%s)',
            $existing ? 'diperbarui' : 'dibuat',
            $user->name,
            $user->email
        ));

        return self::SUCCESS;
    }
}
