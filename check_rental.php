<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$product = App\Models\Product::find(5);
echo 'Product: ' . $product->name . "\n";
echo 'rentalItems count: ' . $product->rentalItems()->count() . "\n";
$result = $product->rentalItems()->exists();
echo 'hasRentalHistory: ' . ($result ? 'true' : 'false') . "\n";