<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\RentalItem;
use App\Models\Rental;

echo "=== All Rental Items ===\n";
$items = RentalItem::with('rental')->get();
foreach ($items as $item) {
    echo 'Item ID: ' . $item->id . ' | Product ID: ' . $item->product_id . ' | Rental ID: ' . $item->rental_id . ' | Rental Status: ' . ($item->rental ? $item->rental->status : 'NULL') . "\n";
}

echo "\n=== All Rentals ===\n";
$rentals = Rental::all();
foreach ($rentals as $r) {
    echo 'Rental ID: ' . $r->id . ' | Status: ' . $r->status . ' | Customer: ' . $r->customer_id . "\n";
}

echo "\n=== Product 8 Details ===\n";
$p8 = Product::find(8);
if ($p8) {
    echo 'Product 8: ' . $p8->name . ' (' . $p8->product_code . ')' . "\n";
    $items8 = RentalItem::where('product_id', 8)->with('rental')->get();
    foreach ($items8 as $item) {
        echo '  Item ID: ' . $item->id . ' | Rental ID: ' . $item->rental_id . ' | Status: ' . ($item->rental ? $item->rental->status : 'NULL') . "\n";
    }
}

echo "\n=== Product 2 Details ===\n";
$p2 = Product::find(2);
if ($p2) {
    echo 'Product 2: ' . $p2->name . ' (' . $p2->product_code . ')' . "\n";
    $items2 = RentalItem::where('product_id', 2)->with('rental')->get();
    foreach ($items2 as $item) {
        echo '  Item ID: ' . $item->id . ' | Rental ID: ' . $item->rental_id . ' | Status: ' . ($item->rental ? $item->rental->status : 'NULL') . "\n";
    }
}

echo "\n=== Product 4 Details ===\n";
$p4 = Product::find(4);
if ($p4) {
    echo 'Product 4: ' . $p4->name . ' (' . $p4->product_code . ')' . "\n";
    $items4 = RentalItem::where('product_id', 4)->with('rental')->get();
    foreach ($items4 as $item) {
        echo '  Item ID: ' . $item->id . ' | Rental ID: ' . $item->rental_id . ' | Status: ' . ($item->rental ? $item->rental->status : 'NULL') . "\n";
    }
}