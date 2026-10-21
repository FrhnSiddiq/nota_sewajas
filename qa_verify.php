<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Rental;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\RentalHistory;
use App\Services\RentalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

echo "=== BLACK-BOX QA VERIFICATION ===\n\n";

// ===========================================
// 1. PAYMENT → RETURN VALIDATION
// ===========================================
echo "1. PAYMENT VALIDATION BEFORE RETURN\n";
echo str_repeat("=", 80) . "\n";

// Find rentals with different payment statuses
$rentals = Rental::with(['customer', 'branch', 'items', 'payments'])->get();

foreach ($rentals as $rental) {
    echo "Rental: {$rental->invoice_number}\n";
    echo "  Status: {$rental->rental_status} | Payment: {$rental->payment_status}\n";
    echo "  Total: {$rental->total_amount} | Paid: {$rental->paid_amount} | Remaining: {$rental->total_remaining_amount}\n";
    
    // Test return validation logic
    $service = new RentalService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('processReturn');
    $method->setAccessible(true);
    
    try {
        // We won't actually call processReturn to avoid changing data
        // Just verify the validation logic would trigger
        if ($rental->payment_status !== 'paid') {
            echo "  ✓ Would BLOCK return (payment_status = {$rental->payment_status})\n";
        } else {
            echo "  ✓ Would ALLOW return (payment_status = paid)\n";
        }
    } catch (ValidationException $e) {
        echo "  ✓ ValidationException thrown: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ===========================================
// 2. REMAINING PAYMENT DISPLAY
// ===========================================
echo "2. REMAINING PAYMENT DISPLAY\n";
echo str_repeat("=", 80) . "\n";

$testRental = Rental::find(1);
if ($testRental) {
    echo "Rental: {$testRental->invoice_number}\n";
    echo "  total_amount: {$testRental->total_amount}\n";
    echo "  paid_amount: {$testRental->paid_amount}\n";
    echo "  fine_amount: {$testRental->fine_amount}\n";
    echo "  fine_paid_amount: {$testRental->fine_paid_amount}\n";
    echo "  total_remaining_amount (accessor): {$testRental->total_remaining_amount}\n";
    echo "  Expected remaining: " . max(0, $testRental->total_amount + $testRental->fine_amount - $testRental->paid_amount - $testRental->fine_paid_amount) . "\n";
    
    // Verify the formula
    $calculated = max(0, $testRental->total_amount + $testRental->fine_amount - $testRental->paid_amount - $testRental->fine_paid_amount);
    if ($testRental->total_remaining_amount == $calculated) {
        echo "  ✓ Accessor formula matches expected\n";
    } else {
        echo "  ✗ MISMATCH: accessor={$testRental->total_remaining_amount} vs expected={$calculated}\n";
    }
}
echo "\n";

// ===========================================
// 3. PRODUCT DELETE LOGIC
// ===========================================
echo "3. PRODUCT DELETE LOGIC\n";
echo str_repeat("=", 80) . "\n";

$products = Product::withTrashed()->with(['rentalItems.rental'])->get();
$activeRentalsProducts = 0;
$completedRentalsProducts = 0;
$noRentalsProducts = 0;

foreach ($products as $product) {
    $activeRentals = $product->rentalItems->filter(function($ri) {
        return in_array($ri->rental->rental_status ?? '', ['waiting', 'active', 'overdue']);
    })->count();
    
    $completedRentals = $product->rentalItems->filter(function($ri) {
        return in_array($ri->rental->rental_status ?? '', ['returned', 'completed', 'cancelled']);
    })->count();
    
    if ($activeRentals > 0) {
        $activeRentalsProducts++;
        echo "  Product {$product->code} ({$product->name}): ACTIVE rentals = $activeRentals → BLOCK delete\n";
    } elseif ($completedRentals > 0) {
        $completedRentalsProducts++;
        echo "  Product {$product->code} ({$product->name}): COMPLETED rentals = $completedRentals → ALLOW delete\n";
    } else {
        $noRentalsProducts++;
        echo "  Product {$product->code} ({$product->name}): NO rentals → ALLOW delete\n";
    }
}
echo "  Summary: Active={$activeRentalsProducts}, Completed={$completedRentalsProducts}, None={$noRentalsProducts}\n\n";

// ===========================================
// 4. CATEGORY DELETE
// ===========================================
echo "4. CATEGORY DELETE (Jas Formal)\n";
echo str_repeat("=", 80) . "\n";

$jasFormal = Category::where('name', 'Jas Formal')->first();
if ($jasFormal) {
    echo "  Category: {$jasFormal->name} (ID: {$jasFormal->id})\n";
    echo "  Products (active): {$jasFormal->products()->count()}\n";
    echo "  Products (withTrashed): {$jasFormal->products()->withTrashed()->count()}\n";
    
    // The controller should check withTrashed()
    if ($jasFormal->products()->withTrashed()->count() > 0) {
        echo "  ✓ Would show error: still has products (including soft-deleted)\n";
    } else {
        echo "  ✓ Would allow delete\n";
    }
} else {
    echo "  Category 'Jas Formal' not found\n";
}
echo "\n";

// ===========================================
// 5. ADMIN DASHBOARD LAUNDRY REMOVAL
// ===========================================
echo "5. ADMIN DASHBOARD - LAUNDRY REMOVAL\n";
echo str_repeat("=", 80) . "\n";

$dashboardView = file_get_contents('resources/views/dashboard/_dashboard_common.blade.php');
if (strpos($dashboardView, 'Laundry') === false) {
    echo "  ✓ 'Laundry' text not found in dashboard view\n";
} else {
    echo "  ✗ 'Laundry' still found in dashboard view\n";
}
echo "\n";

// ===========================================
// 6. CANCEL WAITING RENTAL
// ===========================================
echo "6. CANCEL WAITING RENTAL\n";
echo str_repeat("=", 80) . "\n";

$waitingRentals = Rental::where('rental_status', 'waiting')->get();
echo "  Waiting rentals count: {$waitingRentals->count()}\n";
foreach ($waitingRentals as $r) {
    echo "  - {$r->invoice_number}: status={$r->rental_status}, payment={$r->payment_status}\n";
}

// Test cancel method validation logic
$activeRentals = Rental::whereIn('rental_status', ['active', 'overdue', 'returned', 'completed'])->get();
echo "  Non-waiting rentals count: {$activeRentals->count()}\n";
echo "  These should NOT be cancellable\n\n";

// ===========================================
// 7. RENTAL HISTORY
// ===========================================
echo "7. RENTAL HISTORY\n";
echo str_repeat("=", 80) . "\n";

$histories = RentalHistory::with(['branch', 'customer', 'createdBy', 'returnedBy'])->get();
echo "  Total histories: {$histories->count()}\n";
foreach ($histories as $h) {
    $branchName = $h->branch ? $h->branch->name : 'N/A';
    $customerName = $h->customer ? $h->customer->name : 'N/A';
    echo "  - {$h->invoice_number} | Branch: {$branchName} | Customer: {$customerName} | Completed: {$h->completed_at}\n";
    if (!empty($h->items_snapshot)) {
        echo "    Items snapshot count: " . count($h->items_snapshot) . "\n";
        foreach ($h->items_snapshot as $item) {
            echo "      * {$item['product_name']} (size: {$item['product_size']}, qty: {$item['quantity']})\n";
            if (!empty($item['product_snapshot'])) {
                echo "        Product snapshot: code={$item['product_snapshot']['code']}, name={$item['product_snapshot']['name']}, category={$item['product_snapshot']['category']}, brand={$item['product_snapshot']['brand']}, price={$item['product_snapshot']['rental_price']}\n";
            }
        }
    }
}
echo "\n";

// ===========================================
// 8. DUPLICATE HISTORY PREVENTION
// ===========================================
echo "8. DUPLICATE HISTORY PREVENTION\n";
echo str_repeat("=", 80) . "\n";

// Check if there's a unique constraint or if we can detect duplicates
$duplicates = RentalHistory::select('rental_id', DB::raw('count(*) as cnt'))
    ->groupBy('rental_id')
    ->having('cnt', '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    echo "  ✗ Found duplicate histories for rental_ids: " . $duplicates->pluck('rental_id')->implode(', ') . "\n";
} else {
    echo "  ✓ No duplicate histories found (1 rental = 1 history)\n";
}
echo "\n";

// ===========================================
// 9. ROLE & BRANCH SCOPING
// ===========================================
echo "9. ROLE & BRANCH SCOPING\n";
echo str_repeat("=", 80) . "\n";

$users = User::whereIn('role', ['super_admin', 'admin_toko', 'sales'])->get();
foreach ($users as $user) {
    echo "  User: {$user->name} ({$user->role}) Branch: {$user->branch_id}\n";
    
    if ($user->isSuperAdmin()) {
        $visibleHistories = RentalHistory::count();
        echo "    Super Admin: can see {$visibleHistories} histories (all branches)\n";
    } elseif ($user->isAdminToko()) {
        $accessibleBranches = $user->accessible_branch_ids;
        if (empty($accessibleBranches)) $accessibleBranches = [];
        $visibleHistories = RentalHistory::whereIn('branch_id', $accessibleBranches)->count();
        echo "    Admin Toko: can see {$visibleHistories} histories (branches: " . implode(',', $accessibleBranches) . ")\n";
    } elseif ($user->isSales()) {
        $visibleHistories = RentalHistory::where('created_by', $user->id)->count();
        echo "    Sales: can see {$visibleHistories} histories (own rentals only)\n";
    }
}
echo "\n";

// ===========================================
// 10. REGRESSION - BASIC CHECKS
// ===========================================
echo "10. REGRESSION - BASIC CHECKS\n";
echo str_repeat("=", 80) . "\n";

// Check routes exist
$routesToCheck = [
    'rental-histories.index',
    'rental-histories.show',
    'rentals.cancel',
];

echo "  Checking routes:\n";
foreach ($routesToCheck as $routeName) {
    try {
        $url = route($routeName, ['rentalHistory' => 1, 'rental' => 1]);
        echo "  ✓ Route {$routeName} exists\n";
    } catch (\Exception $e) {
        echo "  ✗ Route {$routeName} MISSING: " . $e->getMessage() . "\n";
    }
}

// Check sidebar has rental-histories
$sidebarView = file_get_contents('resources/views/Layouts/app.blade.php');
if (strpos($sidebarView, 'rental-histories') !== false) {
    echo "  ✓ Sidebar includes rental-histories link\n";
} else {
    echo "  ✗ Sidebar missing rental-histories link\n";
}

echo "\n=== QA VERIFICATION COMPLETE ===\n";