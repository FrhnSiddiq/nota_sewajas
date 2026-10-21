<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\User::all()->each(function($u) {
    echo $u->email . ' | ' . $u->role . ' | ' . $u->name . PHP_EOL;
});