<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\UserRole;
use App\Models\User;

$password = 'password';

$accounts = [
    ['email' => 'admin@local.test', 'name' => 'Admin Utama', 'role' => UserRole::Admin, 'modules' => ['admin', 'cogs', 'kasir']],
    ['email' => 'cogs@local.test', 'name' => 'Admin COGS', 'role' => UserRole::Cogs, 'modules' => ['cogs']],
    ['email' => 'kasir@local.test', 'name' => 'Kasir Demo', 'role' => UserRole::Kasir, 'modules' => ['kasir']],
];

foreach ($accounts as $account) {
    $user = User::updateOrCreate(
        ['email' => $account['email']],
        [
            'name' => $account['name'],
            'role' => $account['role'],
            'modules' => $account['modules'],
            'password' => $password,
        ]
    );

    echo "{$account['email']} ({$account['role']->value}): OK\n";
}
