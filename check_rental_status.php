<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Rental;

echo "Rental rental_status values in DB:\n";
$statuses = Rental::distinct()->pluck('rental_status');
print_r($statuses->toArray());

echo "\n=== All Rentals with rental_status ===\n";
$rentals = Rental::all();
foreach ($rentals as $r) {
    echo 'Rental ID: ' . $r->id . ' | rental_status: ' . $r->rental_status . ' | Customer: ' . $r->customer_id . "\n";
}