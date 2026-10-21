<?php

require 'C:\xampp82\htdocs\sewajas\vendor\autoload.php';
$app = require 'C:\xampp82\htdocs\sewajas\bootstrap\app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Category;
use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// Login as admin_toko
$user = User::where('email', 'admin.pusat@jasrental.id')->first();
Auth::login($user);
echo "Logged in as: {$user->name} ({$user->email}) - Role: {$user->role}, Branch: {$user->branch_id}\n";

// Test customer creation
echo "\n=== Testing Customer Creation ===\n";
$customerData = [
    'name' => 'Test Customer Debug',
    'phone' => '081234567890',
    'email' => 'test.debug@example.com',
    'address' => 'Jl. Test No. 123, Yogyakarta',
    'id_number' => '3471123456789012',
];

try {
    $customer = Customer::create($customerData);
    echo "SUCCESS: Customer created with ID: {$customer->id}, branch_id: {$customer->branch_id}\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Test product creation
echo "\n=== Testing Product Creation ===\n";
$category = Category::first();
$productData = [
    'category_id' => $category->id,
    'name' => 'Test Product Debug',
    'code' => 'TEST-DEBUG-001',
    'description' => 'Test product',
    'rental_price' => 50000,
    'deposit_price' => 100000,
    'stock_total' => 10,
    'stock_available' => 10,
    'color' => 'Black',
    'brand' => 'Test',
    'condition' => 'excellent',
    'status' => 'available',
    'branch_id' => 1,
    'size' => null,
];

try {
    $product = Product::create($productData);
    echo "SUCCESS: Product created with ID: {$product->id}\n";
    
    // Add size
    $size = ProductSize::create([
        'product_id' => $product->id,
        'size' => 'M',
        'stock_total' => 10,
        'stock_available' => 10,
    ]);
    echo "Size created: {$size->size}\n";
    
    // Sync branch
    $product->branches()->sync([1]);
    echo "Branch synced\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Test rental creation
echo "\n=== Testing Rental Creation ===\n";
$customer = Customer::where('branch_id', 1)->first();
$product = Product::whereHas('sizes', fn($q) => $q->where('stock_available', '>', 0))->first();
$size = ProductSize::where('product_id', $product->id)->where('stock_available', '>', 0)->first();

if ($customer && $product && $size) {
    echo "Customer: {$customer->name} (ID: {$customer->id})\n";
    echo "Product: {$product->name} (ID: {$product->id})\n";
    echo "Size: {$size->size} (ID: {$size->id})\n";
    
    // Use RentalService
    $rentalService = app(\App\Services\RentalService::class);
    
    $rentalData = [
        'customer_id' => $customer->id,
        'rental_date' => date('Y-m-d'),
        'return_due_date' => date('Y-m-d', strtotime('+3 days')),
        'duration_days' => 3,
        'items' => [
            [
                'product_id' => $product->id,
                'product_size' => $size->size,
                'quantity' => 1,
                'price_per_day' => $product->rental_price,
            ]
        ],
        'payment_method' => 'cash',
        'notes' => 'Test rental',
    ];
    
    try {
        $rental = $rentalService->createRental($rentalData);
        echo "SUCCESS: Rental created with ID: {$rental->id}, Invoice: {$rental->invoice_number}\n";
        file_put_contents('/tmp/test_rental_id.txt', $rental->id);
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Missing prerequisites\n";
}

echo "\n=== Test Complete ===\n";