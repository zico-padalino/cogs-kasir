<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$password = 'password';
$hash = Hash::make($password);

echo "New hash: {$hash}\n";

$accounts = [
    ['email' => 'cogs@local.test', 'name' => 'Admin COGS', 'role' => UserRole::Cogs],
    ['email' => 'kasir@local.test', 'name' => 'Kasir Demo', 'role' => UserRole::Kasir],
];

foreach ($accounts as $account) {
    $user = User::updateOrCreate(
        ['email' => $account['email']],
        [
            'name' => $account['name'],
            'role' => $account['role'],
            'password' => $password,
        ]
    );

    $ok = Hash::check($password, $user->fresh()->password) ? 'OK' : 'FAIL';
    echo "{$account['email']} ({$account['role']->value}): {$ok}\n";
}
