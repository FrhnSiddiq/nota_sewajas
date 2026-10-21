<?php

require 'C:\xampp82\htdocs\sewajas\vendor\autoload.php';
$app = require 'C:\xampp82\htdocs\sewajas\bootstrap\app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;

$branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB1', 'address' => 'Test']);
$category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat']);
$product = Product::create([
    'branch_id' => $branch->id,
    'category_id' => $category->id,
    'code' => 'TEST-001',
    'name' => 'Test Product',
    'rental_price' => 100000,
    'deposit_price' => 0,
    'stock_total' => 10,
    'stock_available' => 10,
    'condition' => 'good',
    'status' => 'available',
]);
$size = ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock_total' => 10, 'stock_available' => 10]);

$user = User::factory()->create([
    'name' => 'Sales Test',
    'role' => 'sales',
    'branch_id' => $branch->id,
    'branch_access_type' => 'specific',
]);

echo 'User: ' . $user->name . PHP_EOL;
echo 'User branch_id: ' . $user->branch_id . PHP_EOL;
echo 'User role: ' . $user->role . PHP_EOL;
echo 'User is_active: ' . ($user->is_active ? 'true' : 'false') . PHP_EOL;
echo 'Accessible branch IDs: ' . implode(', ', $user->accessible_branch_ids) . PHP_EOL;
echo 'Product branch_id: ' . $product->branch_id . PHP_EOL;
echo 'Product branches: ' . $product->branches()->pluck('branches.id')->implode(',') . PHP_EOL;