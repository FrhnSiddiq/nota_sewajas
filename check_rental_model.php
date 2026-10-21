<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Rental;

$rental = Rental::first();
echo "Rental attributes:\n";
print_r($rental->getAttributes());

echo "\nRental statuses in DB:\n";
$statuses = Rental::distinct()->pluck('status');
print_r($statuses->toArray());

echo "\nRental fillable:\n";
print_r((new Rental())->getFillable());

echo "\nRental casts:\n";
print_r((new Rental())->getCasts());