<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'superadmin@jasrental.id')->first();
echo "User: " . $user->name . PHP_EOL;
echo "Password hash: " . $user->password . PHP_EOL;
echo "Check password123: " . (password_verify('password123', $user->password) ? 'MATCH' : 'NO MATCH') . PHP_EOL;
echo "Check password: " . (password_verify('password', $user->password) ? 'MATCH' : 'NO MATCH') . PHP_EOL;