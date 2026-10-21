<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\Customer::all()->each(function($c) {
    echo $c->id . ' | ' . $c->name . ' | ' . $c->phone . ' | ' . $c->branch_id . PHP_EOL;
});