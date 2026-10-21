<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\RentalHistory;
use App\Models\RentalItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Category;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Payment;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalHistoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $adminToko;
    protected $sales;
    protected $branch;
    protected $category;
    protected $product;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        // Create branch
        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TST01',
            'address' => 'Test Address',
            'phone' => '021-123456',
            'email' => 'test@jasrental.id',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'is_active' => true,
        ]);

        // Create branch 2
        $this->branch2 = Branch::create([
            'name' => 'Branch 2',
            'code' => 'TST02',
            'address' => 'Test Address 2',
            'phone' => '021-654321',
            'email' => 'test2@jasrental.id',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'is_active' => true,
        ]);

        // Create Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.id',
            'password' => bcrypt('password'),
            'phone' => '081111111111',
            'role' => 'super_admin',
            'branch_id' => null,
            'is_active' => true,
        ]);

        // Create Admin Toko
        $this->adminToko = User::create([
            'name' => 'Admin Toko',
            'email' => 'admintoko@test.id',
            'password' => bcrypt('password'),
            'phone' => '081111111112',
            'role' => 'admin_toko',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Create Sales
        $this->sales = User::create([
            'name' => 'Sales Test',
            'email' => 'sales@test.id',
            'password' => bcrypt('password'),
            'phone' => '081111111113',
            'role' => 'sales',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Create Sales for branch 2
        $this->sales2 = User::create([
            'name' => 'Sales Test 2',
            'email' => 'sales2@test.id',
            'password' => bcrypt('password'),
            'phone' => '081111111114',
            'role' => 'sales',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);

        // Create category
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        // Create product
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'code' => 'PRD-TEST001',
            'name' => 'Test Product',
            'description' => 'Test Description',
            'color' => 'Hitam',
            'brand' => 'Test Brand',
            'rental_price' => 100000,
            'deposit_price' => 200000,
            'stock_total' => 10,
            'stock_available' => 10,
            'condition' => 'excellent',
            'status' => 'available',
        ]);

        // Add product size
        $this->productSize = ProductSize::create([
            'product_id' => $this->product->id,
            'size' => 'M',
            'stock_total' => 10,
            'stock_available' => 10,
        ]);

        // Create product for branch 2
        $this->product2 = Product::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->category->id,
            'code' => 'PRD-TEST002',
            'name' => 'Test Product 2',
            'description' => 'Test Description 2',
            'color' => 'Biru',
            'brand' => 'Test Brand',
            'rental_price' => 150000,
            'deposit_price' => 300000,
            'stock_total' => 10,
            'stock_available' => 10,
            'condition' => 'excellent',
            'status' => 'available',
        ]);

        $this->productSize2 = ProductSize::create([
            'product_id' => $this->product2->id,
            'size' => 'M',
            'stock_total' => 10,
            'stock_available' => 10,
        ]);

        // Create customer
        $this->customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Customer',
            'phone' => '081234567890',
            'email' => 'customer@test.id',
            'address' => 'Test Address',
            'id_type' => 'ktp',
            'id_number' => '1234567890123456',
        ]);

        // Create customer for branch 2
        $this->customer2 = Customer::create([
            'branch_id' => $this->branch2->id,
            'name' => 'Test Customer 2',
            'phone' => '081234567891',
            'email' => 'customer2@test.id',
            'address' => 'Test Address 2',
            'id_type' => 'ktp',
            'id_number' => '1234567890123457',
        ]);
    }

    public function test_complete_rental_flow_creates_history_with_snapshot()
    {
        // 1. Create rental as Super Admin
        $rental = Rental::create([
            'invoice_number' => 'INV-TEST-001',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'rental_status' => 'waiting',
            'notes' => 'Test rental',
        ]);

        // Add rental item
        $item = RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_size' => 'M',
            'product_color' => $this->product->color,
            'quantity' => 1,
            'price_per_day' => 100000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        // 2. Make full payment (lunas)
        $rental->update([
            'payment_status' => 'paid',
            'paid_amount' => 300000,
        ]);

        // Create payment record
        Payment::create([
            'rental_id' => $rental->id,
            'payment_number' => 'PAY-001',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);

        // 3. Handover rental (waiting -> active)
        $rental->update(['rental_status' => 'active']);

        // 4. Process return via RentalService (this should create history)
        $rentalService = app(RentalService::class);
        $rental = $rental->fresh();

        // Verify preconditions
        $this->assertEquals('active', $rental->rental_status);
        $this->assertEquals('paid', $rental->payment_status);

        // Authenticate superAdmin for the service call
        $this->actingAs($this->superAdmin);

        // Process return
        $returnData = [
            'items' => [
                [
                    'rental_item_id' => $item->id,
                    'condition' => 'baik',
                    'damage_fee' => 0,
                    'notes' => 'Dikembalikan dengan baik',
                ],
            ],
        ];

        $returnedRental = $rentalService->processReturn($rental, $returnData);

        // 5. Verify rental is returned
        $this->assertEquals('returned', $returnedRental->rental_status);
        $this->assertNotNull($returnedRental->actual_return_date);
        $this->assertNotNull($returnedRental->returned_by);

        // 6. Verify rental history was created
        $history = RentalHistory::where('rental_id', $rental->id)->first();
        $this->assertNotNull($history, 'Rental history should be created after return');

        // 7. Verify history contains correct data
        $this->assertEquals($returnedRental->id, $history->rental_id);
        $this->assertEquals($returnedRental->invoice_number, $history->invoice_number);
        $this->assertEquals($returnedRental->branch_id, $history->branch_id);
        $this->assertEquals($returnedRental->customer_id, $history->customer_id);
        $this->assertEquals($returnedRental->created_by, $history->created_by);
        $this->assertEquals($returnedRental->returned_by, $history->returned_by);
        $this->assertEquals($returnedRental->total_amount, $history->total_amount);
        $this->assertEquals($returnedRental->paid_amount, $history->paid_amount);
        $this->assertEquals($returnedRental->rental_date, $history->rental_date);
        $this->assertEquals($returnedRental->return_due_date, $history->return_due_date);
        $this->assertEquals($returnedRental->actual_return_date, $history->actual_return_date);
        $this->assertNotNull($history->completed_at);

        // 8. Verify items snapshot contains product details
        $this->assertNotEmpty($history->items_snapshot);
        $itemsSnapshot = $history->items_snapshot;
        $this->assertCount(1, $itemsSnapshot);

        $snapshotItem = $itemsSnapshot[0];
        $this->assertEquals($item->product_name, $snapshotItem['product_name']);
        $this->assertEquals($item->product_size, $snapshotItem['product_size']);
        $this->assertEquals($item->quantity, $snapshotItem['quantity']);
        $this->assertEquals((float)$item->price_per_day, $snapshotItem['price_per_day']);
        $this->assertEquals((float)$item->subtotal, $snapshotItem['subtotal']);

        // 9. Verify product snapshot is preserved
        $this->assertNotEmpty($snapshotItem['product_snapshot']);
        $productSnapshot = $snapshotItem['product_snapshot'];
        $this->assertEquals($this->product->code, $productSnapshot['code']);
        $this->assertEquals($this->product->name, $productSnapshot['name']);
        $this->assertEquals($this->category->name, $productSnapshot['category']);
        $this->assertEquals($this->product->brand, $productSnapshot['brand']);
        $this->assertEquals($this->product->color, $productSnapshot['color']);
        $this->assertEquals((float)$this->product->rental_price, $productSnapshot['rental_price']);
        $this->assertEquals($this->product->condition, $productSnapshot['condition']);

        // 10. Verify guarantees and payments snapshots
        $this->assertIsArray($history->guarantees_snapshot);
        $this->assertIsArray($history->payments_snapshot);
        $this->assertCount(1, $history->payments_snapshot);
        $paymentSnapshot = $history->payments_snapshot[0];
        $this->assertEquals('PAY-001', $paymentSnapshot['payment_number']);
        $this->assertEquals(300000, $paymentSnapshot['amount']);
    }

    public function test_history_not_created_if_not_paid()
    {
        $rental = Rental::create([
            'invoice_number' => 'INV-TEST-002',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 100000, // partial payment
            'payment_status' => 'partial',
            'rental_status' => 'active',
        ]);

        $item = RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_size' => 'M',
            'product_color' => $this->product->color,
            'quantity' => 1,
            'price_per_day' => 100000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        $rentalService = app(RentalService::class);
        $rental = $rental->fresh();

        // Try to return with partial payment - should throw ValidationException
        $returnData = [
            'items' => [
                [
                    'rental_item_id' => $item->id,
                    'condition' => 'baik',
                    'damage_fee' => 0,
                    'notes' => 'Test',
                ],
            ],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('pembayaran belum lunas');

        $rentalService->processReturn($rental, $returnData);

        // Verify no history was created
        $this->assertDatabaseMissing('rental_histories', ['rental_id' => $rental->id]);
    }

    public function test_only_one_history_per_rental()
    {
        $rental = Rental::create([
            'invoice_number' => 'INV-TEST-003',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'payment_status' => 'paid',
            'rental_status' => 'active',
        ]);

        $item = RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_size' => 'M',
            'product_color' => $this->product->color,
            'quantity' => 1,
            'price_per_day' => 100000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        Payment::create([
            'rental_id' => $rental->id,
            'payment_number' => 'PAY-002',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);

        $rentalService = app(RentalService::class);
        $rental = $rental->fresh();

        // First return
        $this->actingAs($this->superAdmin);
        $returnData = [
            'items' => [
                [
                    'rental_item_id' => $item->id,
                    'condition' => 'baik',
                    'damage_fee' => 0,
                    'notes' => 'First return',
                ],
            ],
        ];

        $rentalService->processReturn($rental, $returnData);

        // Try to return again (should fail or not create duplicate history)
        $rental = $rental->fresh();
        $this->assertEquals('returned', $rental->rental_status);

        // Count histories - should be exactly 1
        $historyCount = RentalHistory::where('rental_id', $rental->id)->count();
        $this->assertEquals(1, $historyCount, 'Only one history should be created per rental');

        // Test database-level unique constraint prevents duplicates
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('Duplicate entry');
        RentalHistory::create([
            'rental_id' => $rental->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'returned_by' => $this->superAdmin->id,
            'invoice_number' => 'DUPLICATE',
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'actual_return_date' => now()->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'fine_amount' => 0,
            'fine_paid_amount' => 0,
            'payment_status' => 'paid',
            'fine_status' => 'none',
            'items_snapshot' => [],
            'guarantees_snapshot' => [],
            'payments_snapshot' => [],
            'completed_at' => now(),
        ]);
    }

    public function test_history_preserved_after_product_is_permanently_deleted()
    {
        $rental = Rental::create([
            'invoice_number' => 'INV-TEST-004',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'payment_status' => 'paid',
            'rental_status' => 'active',
        ]);

        $item = RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_size' => 'M',
            'product_color' => $this->product->color,
            'quantity' => 1,
            'price_per_day' => 100000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        Payment::create([
            'rental_id' => $rental->id,
            'payment_number' => 'PAY-003',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);

        $rentalService = app(RentalService::class);
        $rental = $rental->fresh();

        $this->actingAs($this->superAdmin);

        $returnData = [
            'items' => [
                [
                    'rental_item_id' => $item->id,
                    'condition' => 'baik',
                    'damage_fee' => 0,
                    'notes' => 'Test',
                ],
            ],
        ];

        $rentalService->processReturn($rental, $returnData);

        // Verify history exists
        $history = RentalHistory::where('rental_id', $rental->id)->first();
        $this->assertNotNull($history);

        $productCodeBefore = $history->items_snapshot[0]['product_snapshot']['code'];

        $this->delete(route('products.destroy', $this->product))
            ->assertRedirect(route('products.index'));

        // Refresh history
        $history->refresh();

        // History should still have product snapshot
        $productSnapshot = $history->items_snapshot[0]['product_snapshot'];
        $this->assertEquals($productCodeBefore, $productSnapshot['code']);
        $this->assertEquals($this->product->name, $productSnapshot['name']);
        $this->assertEquals($this->category->name, $productSnapshot['category']);
        $this->assertEquals($this->product->brand, $productSnapshot['brand']);
        $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
        $this->assertDatabaseHas('rental_items', [
            'id' => $item->id,
            'product_id' => null,
            'product_name' => $this->product->name,
        ]);
    }

    public function test_returned_rental_with_unpaid_damage_fee_stays_in_rentals_until_settled()
    {
        $rental = Rental::create([
            'invoice_number' => 'INV-UNPAID-FINE',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'payment_status' => 'paid',
            'rental_status' => 'active',
            'fine_status' => 'none',
            'fine_amount' => 0,
            'fine_paid_amount' => 0,
        ]);

        $item = RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_size' => 'M',
            'product_color' => $this->product->color,
            'quantity' => 1,
            'price_per_day' => 100000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        Payment::create([
            'rental_id' => $rental->id,
            'payment_number' => 'PAY-UNPAID-FINE',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin);
        $rentalService = app(RentalService::class);
        $rentalService->processReturn($rental, [
            'items' => [[
                'rental_item_id' => $item->id,
                'condition' => 'rusak_ringan',
                'damage_fee' => 50000,
                'notes' => 'Denda kerusakan',
            ]],
        ]);

        $this->assertSame('returned', $rental->fresh()->rental_status);
        $this->assertSame('Denda belum lunas', $rental->fresh()->status_label);
        $this->assertFalse($rental->fresh()->isCompleted());
        $this->assertSame(0, Rental::completed()->count());
        $this->assertDatabaseMissing('rental_histories', ['rental_id' => $rental->id]);
        $this->get(route('rentals.index'))
            ->assertOk()
            ->assertSee('Denda belum lunas')
            ->assertSee('INV-UNPAID-FINE');
        $this->get(route('rental-histories.index'))
            ->assertOk()
            ->assertDontSee('INV-UNPAID-FINE');

        $rentalService->processPayment($rental->fresh(), [
            'amount' => 50000,
            'method' => 'cash',
            'payment_type' => 'damage_fee',
        ]);

        $this->assertDatabaseHas('rental_histories', [
            'rental_id' => $rental->id,
            'payment_status' => 'paid',
            'fine_status' => 'paid',
        ]);
        $this->assertSame('Selesai', $rental->fresh()->status_label);
        $this->assertTrue($rental->fresh()->isCompleted());
        $this->assertSame(1, Rental::completed()->count());
        $this->get(route('rentals.index'))
            ->assertOk()
            ->assertDontSee('INV-UNPAID-FINE');
        $this->get(route('rental-histories.index'))
            ->assertOk()
            ->assertSee('INV-UNPAID-FINE');
    }

    public function test_returned_rental_with_unpaid_rental_amount_stays_in_rentals()
    {
        $rental = Rental::create([
            'invoice_number' => 'INV-UNPAID-RENTAL',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->superAdmin->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'actual_return_date' => now()->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 100000,
            'payment_status' => 'partial',
            'rental_status' => 'returned',
            'fine_status' => 'none',
            'fine_amount' => 0,
            'fine_paid_amount' => 0,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('rentals.index'))
            ->assertOk()
            ->assertSee('Belum lunas')
            ->assertSee('INV-UNPAID-RENTAL');
        $this->get(route('rental-histories.index'))
            ->assertOk()
            ->assertDontSee('INV-UNPAID-RENTAL');
    }

    public function test_history_scope_by_role()
    {
        // sales2 is already created in setUp
        $sales2 = $this->sales2;

        // Create rental by sales on branch 1
        $rental1 = Rental::create([
            'invoice_number' => 'INV-TEST-005',
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->sales->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'payment_status' => 'paid',
            'rental_status' => 'active',
        ]);

        $item1 = RentalItem::create([
            'rental_id' => $rental1->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_size' => 'M',
            'product_color' => $this->product->color,
            'quantity' => 1,
            'price_per_day' => 100000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        Payment::create([
            'rental_id' => $rental1->id,
            'payment_number' => 'PAY-005',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);

        // Create rental by sales2 on branch 2
        $customer2 = Customer::create([
            'branch_id' => $this->branch2->id,
            'name' => 'Test Customer 2',
            'phone' => '081234567892',  // Different phone
            'email' => 'customer2@test.id',
            'address' => 'Test Address 2',
            'id_type' => 'ktp',
            'id_number' => '1234567890123457',
        ]);

        $rental2 = Rental::create([
            'invoice_number' => 'INV-TEST-006',
            'branch_id' => $this->branch2->id,
            'customer_id' => $this->customer2->id,
            'created_by' => $this->sales2->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDays(3)->toDateString(),
            'duration_days' => 3,
            'subtotal' => 300000,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'payment_status' => 'paid',
            'rental_status' => 'active',
        ]);

        $item2 = RentalItem::create([
            'rental_id' => $rental2->id,
            'product_id' => $this->product2->id,
            'product_name' => $this->product2->name,
            'product_size' => 'M',
            'product_color' => $this->product2->color,
            'quantity' => 1,
            'price_per_day' => 150000,
            'duration_days' => 3,
            'subtotal' => 300000,
        ]);

        Payment::create([
            'rental_id' => $rental2->id,
            'payment_number' => 'PAY-006',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);

        $rentalService = app(RentalService::class);

        // Process both returns
        $this->actingAs($this->superAdmin);
        $rentalService->processReturn($rental1->fresh(), [
            'items' => [['rental_item_id' => $item1->id, 'condition' => 'baik', 'damage_fee' => 0, 'notes' => 'Test']],
        ]);

        $rentalService->processReturn($rental2->fresh(), [
            'items' => [['rental_item_id' => $item2->id, 'condition' => 'baik', 'damage_fee' => 0, 'notes' => 'Test']],
        ]);

        $history1 = RentalHistory::where('rental_id', $rental1->id)->firstOrFail();
        $history2 = RentalHistory::where('rental_id', $rental2->id)->firstOrFail();

        // Test Super Admin sees all
        $superAdminHistories = RentalHistory::whereIn('branch_id', [$this->branch->id, $this->branch2->id])->count();
        $this->assertEquals(2, $superAdminHistories);

        // Admin Toko has operational access across branches.
        $adminHistories = RentalHistory::whereIn('branch_id', $this->adminToko->accessible_branch_ids)->count();
        $this->assertEquals(2, $adminHistories);

        // Histories retain the original transaction creator.
        $salesHistories = RentalHistory::where('created_by', $this->sales->id)->count();
        $this->assertEquals(1, $salesHistories);

        $sales2Histories = RentalHistory::where('created_by', $this->sales2->id)->count();
        $this->assertEquals(1, $sales2Histories);

        $this->actingAs($this->sales)
            ->get(route('rental-histories.index'))
            ->assertOk()
            ->assertSee($history1->invoice_number)
            ->assertDontSee($history2->invoice_number);
        $this->get(route('rental-histories.show', $history1))->assertOk();
        $this->get(route('rental-histories.show', $history2))->assertForbidden();
        $this->get(route('rental-histories.invoice', $history2))->assertForbidden();

        $this->actingAs($this->adminToko)
            ->get(route('rental-histories.index'))
            ->assertOk()
            ->assertSee($history1->invoice_number)
            ->assertSee($history2->invoice_number);
        $this->assertTrue($this->adminToko->canAccessBranch($this->branch2->id));
        $this->get(route('rentals.show', $rental2))->assertOk();
        $this->get(route('rental-histories.show', $history1))->assertOk();
        $this->get(route('rental-histories.show', $history2))->assertOk();
        $this->get(route('rental-histories.invoice', $history2))->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('rental-histories.index'))
            ->assertOk()
            ->assertSee($history1->invoice_number)
            ->assertSee($history2->invoice_number);
        $this->get(route('rental-histories.show', $history1))->assertOk();
        $this->get(route('rental-histories.show', $history2))->assertOk();

        $this->actingAs($this->sales)
            ->get(route('rental-histories.show', $history1))
            ->assertOk()
            ->assertSee('Sales Test')
            ->assertSee('Super Admin');

        $invoiceHtml = view('rental_histories.invoice', ['history' => $history1->load(['branch', 'customer', 'createdBy', 'returnedBy'])])->render();
        $this->assertStringContainsString('Sales Test', $invoiceHtml);
        $this->assertStringContainsString('Super Admin', $invoiceHtml);

        $invoiceResponse = $this->get(route('rental-histories.invoice', $history1));
        $invoiceResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=INV-TEST-005-invoice.pdf');
        $this->assertStringStartsWith('%PDF', $invoiceResponse->getContent());
    }
}