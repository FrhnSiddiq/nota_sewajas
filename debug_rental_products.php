<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::where('email', 'andi.pratama@jasrental.id')->first();
echo "User: {$user->name}, Role: {$user->role}, Branch: {$user->branch_id}\n";
echo "branch_access_type: {$user->branch_access_type}\n";
echo "accessible_branch_ids: " . json_encode($user->accessible_branch_ids) . "\n";

$products = App\Models\Product::when(!$user->isSuperAdmin() && ($user->isAdminToko() || $user->branch_access_type === 'specific'), function($q) use ($user) {
    $q->whereHas('branches', fn($bq) => $bq->whereIn('branches.id', $user->accessible_branch_ids));
})->where("status", "available")->where("stock_available", ">", 0)->with('sizes')->get();

echo "Products found: " . $products->count() . "\n";
foreach($products as $p) {
    echo "  - {$p->name} (id={$p->id}, stock_available={$p->stock_available})\n";
}