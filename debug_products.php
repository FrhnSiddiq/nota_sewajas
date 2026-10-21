<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::where('email', 'andi.pratama@jasrental.id')->first();
echo "User: {$user->name}, Role: {$user->role}, Branch: {$user->branch_id}\n";
echo "accessible_branch_ids: " . json_encode($user->accessible_branch_ids) . "\n";

$query = App\Models\Product::with(['category', 'branches', 'sizes'])
    ->whereHas('branches', fn($bq) => $bq->whereIn('branches.id', $user->accessible_branch_ids));

$products = $query->get();
echo "Products found: " . $products->count() . "\n";
foreach($products as $p) {
    echo "  - {$p->name} (id={$p->id}, branch_id={$p->branch_id})\n";
    $branchNames = $p->branches->pluck('name')->implode(', ');
    echo "    branches: {$branchNames}\n";
}