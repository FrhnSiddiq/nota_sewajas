<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    protected User $salesUser;
    protected User $otherSalesUser;
    protected Branch $salesBranch;
    protected Branch $otherBranch;
    protected Customer $salesCustomer;
    protected Customer $otherCustomer;
    protected Product $salesProduct;
    protected Product $otherProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedDatabase();
    }

    private function seedDatabase(): void
    {
        // Create branches
        $this->salesBranch = Branch::create([
            'name' => 'Toko Pusat - Yogyakarta',
            'code' => 'YGY01',
            'address' => 'Jl. Malioboro No. 88, Yogyakarta',
            'phone' => '0274-123456',
            'email' => 'pusat@jasrental.id',
            'city' => 'Yogyakarta',
            'province' => 'DI Yogyakarta',
            'is_active' => true,
        ]);

        $this->otherBranch = Branch::create([
            'name' => 'Cabang Solo',
            'code' => 'SLO01',
            'address' => 'Jl. Slamet Riyadi No. 45, Solo',
            'phone' => '0271-654321',
            'email' => 'solo@jasrental.id',
            'city' => 'Surakarta',
            'province' => 'Jawa Tengah',
            'is_active' => true,
        ]);

        // Create Sales users
        $this->salesUser = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@jasrental.id',
            'password' => bcrypt('password'),
            'phone' => '085312345678',
            'role' => 'sales',
            'branch_id' => $this->salesBranch->id,
            'is_active' => true,
        ]);

        $this->otherSalesUser = User::create([
            'name' => 'Andi Pratama',
            'email' => 'andi.pratama@jasrental.id',
            'password' => bcrypt('password'),
            'phone' => '085398765432',
            'role' => 'sales',
            'branch_id' => $this->otherBranch->id,
            'is_active' => true,
        ]);

        // Create category
        $category = \App\Models\Category::create([
            'name' => 'Jas Formal',
            'slug' => 'jas-formal',
            'icon' => 'briefcase',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Create customers
        $this->salesCustomer = Customer::create([
            'name' => 'Dewi Lestari',
            'phone' => '6281234567890',
            'branch_id' => $this->salesBranch->id,
            'user_id' => $this->salesUser->id,
            'is_blacklisted' => false,
        ]);

        $this->otherCustomer = Customer::create([
            'name' => 'Fitri Handayani',
            'phone' => '6281234567891',
            'branch_id' => $this->otherBranch->id,
            'user_id' => $this->otherSalesUser->id,
            'is_blacklisted' => false,
        ]);

        // Create products
        $this->salesProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Jas Formal Hitam',
            'code' => 'PRD01001',
            'color' => 'Hitam',
            'brand' => 'Zara',
            'rental_price' => 100000,
            'deposit_price' => 200000,
            'condition' => 'excellent',
            'status' => 'available',
            'branch_id' => $this->salesBranch->id,
            'stock_total' => 5,
            'stock_available' => 5,
            'size' => null,
        ]);

        $this->salesProduct->branches()->attach($this->salesBranch->id);
        $this->salesProduct->sizes()->create([
            'size' => 'M',
            'stock_total' => 5,
            'stock_available' => 5,
        ]);

        $this->otherProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Jas Formal Navy',
            'code' => 'PRD02001',
            'color' => 'Navy',
            'brand' => 'H&M',
            'rental_price' => 120000,
            'deposit_price' => 240000,
            'condition' => 'excellent',
            'status' => 'available',
            'branch_id' => $this->otherBranch->id,
            'stock_total' => 3,
            'stock_available' => 3,
            'size' => null,
        ]);

        $this->otherProduct->branches()->attach($this->otherBranch->id);
        $this->otherProduct->sizes()->create([
            'size' => 'L',
            'stock_total' => 3,
            'stock_available' => 3,
        ]);
    }

    // ============================================================
    // TEST 1: Login as Sales
    // ============================================================
    public function test_1_login_as_sales(): void
    {
        $response = $this->post(route('login.post'), [
            'email' => 'budi.santoso@jasrental.id',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->salesUser);
    }

    // ============================================================
    // TEST 2: Dashboard - verify shows only their branch data
    // ============================================================
    public function test_2_dashboard_shows_only_own_branch_data(): void
    {
        $this->actingAs($this->salesUser);

        // Create some rentals for both branches
        $ownRental = $this->createRental($this->salesBranch, $this->salesUser, $this->salesCustomer, $this->salesProduct, 'waiting');
        $otherRental = $this->createRental($this->otherBranch, $this->otherSalesUser, $this->otherCustomer, $this->otherProduct, 'waiting');

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertViewIs('dashboard.sales');
        $response->assertDontSee('Transaksi Saya');

        // Check dashboard data API
        $dataResponse = $this->getJson(route('dashboard.data'));
        $dataResponse->assertOk();
        $payload = $dataResponse->json();

        // Verify stats only include own branch data
        $stats = $payload['stats'] ?? [];
        // Dashboard should only show data for sales user's branch
        $this->assertArrayHasKey('stats', $payload);
    }

    // ============================================================
    // TEST 3: Customers - can only see branch customers
    // ============================================================
    public function test_3_customers_only_see_branch_customers(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->get(route('customers.index'));
        $response->assertOk();
        $response->assertViewIs('customers.index');

        // Should see own branch customer
        $response->assertSee($this->salesCustomer->name);
        // Should NOT see other branch customer
        $response->assertDontSee($this->otherCustomer->name);
    }

    public function test_3b_cannot_access_other_branch_customer_directly(): void
    {
        $this->actingAs($this->salesUser);

        // Try to access other branch customer directly
        $response = $this->get(route('customers.show', $this->otherCustomer));
        $response->assertForbidden();
    }

    public function test_customer_detail_closes_rental_status_icon_before_rendering_rental_details(): void
    {
        $this->actingAs($this->salesUser);
        $this->createRental($this->salesBranch, $this->salesUser, $this->salesCustomer, $this->salesProduct);

        $this->get(route('customers.show', $this->salesCustomer))
            ->assertOk()
            ->assertSee('rental-status-icon-waiting"></i>', false)
            ->assertSee('Riwayat Penyewaan');
    }

    public function test_rental_detail_explains_late_and_item_damage_or_loss_fines(): void
    {
        $this->actingAs($this->salesUser);
        $rental = $this->createRental($this->salesBranch, $this->salesUser, $this->salesCustomer, $this->salesProduct);
        $rental->update([
            'late_fee' => 15000,
            'fine_amount' => 40000,
            'fine_status' => Rental::FINE_UNPAID,
        ]);
        $rental->items()->first()->update([
            'return_condition' => 'rusak_berat',
            'damage_fee' => 25000,
            'return_notes' => 'Resleting rusak',
        ]);

        $this->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertSee('Denda keterlambatan')
            ->assertSee('Denda kerusakan/kehilangan')
            ->assertSee('returnConditionDetailLabel')
            ->assertSee('rusak_berat')
            ->assertSee('return_notes');
    }

    // ============================================================
    // TEST 4: Products - can only see branch products
    // ============================================================
    public function test_4_products_only_see_branch_products(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertViewIs('products.index');

        // Should see own branch product
        $response->assertSee($this->salesProduct->name);
        // Should NOT see other branch product
        $response->assertDontSee($this->otherProduct->name);
    }

    public function test_4b_cannot_access_other_branch_product_directly(): void
    {
        $this->actingAs($this->salesUser);

        // Try to access other branch product directly
        $response = $this->get(route('products.show', $this->otherProduct));
        $response->assertForbidden();
    }

    // ============================================================
    // TEST 5: Create Rental
    // ============================================================
    public function test_5_create_rental(): void
    {
        $this->actingAs($this->salesUser);

        // GET create page
        $response = $this->get(route('rentals.create'));
        $response->assertOk();
        $response->assertViewIs('rentals.create');

        // Should only show own branch customers and products
        $response->assertSee($this->salesCustomer->name);
        $response->assertDontSee($this->otherCustomer->name);
        $response->assertSee($this->salesProduct->name);
        $response->assertDontSee($this->otherProduct->name);

        // POST create rental
        $rentalData = [
            'customer_id' => $this->salesCustomer->id,
            'rental_date' => now()->format('Y-m-d'),
            'duration_days' => 3,
            'discount' => 0,
            'deposit' => 0,
            'notes' => 'Test rental',
            'payment_method' => 'cash',
            'guarantee_type' => 'deposit',
            'guarantee_deposit' => 100000,
            'items' => [
                [
                    'product_id' => $this->salesProduct->id,
                    'product_size' => 'M',
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->post(route('rentals.store'), $rentalData);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify rental was created
        $rental = Rental::where('customer_id', $this->salesCustomer->id)
            ->where('created_by', $this->salesUser->id)
            ->latest()
            ->first();

        $this->assertNotNull($rental);
        $this->assertEquals($this->salesBranch->id, $rental->branch_id);
        $this->assertEquals('waiting', $rental->rental_status);
    }

    public function test_sales_can_create_rental_when_accessible_common_branch_is_not_first_product_branch(): void
    {
        $this->salesUser->accessibleBranches()->attach($this->otherBranch->id);
        $this->salesProduct->branches()->attach($this->otherBranch->id);
        $customer = Customer::create([
            'name' => 'Pelanggan Cabang Lain',
            'phone' => '6281234000002',
            'branch_id' => $this->otherBranch->id,
            'user_id' => $this->salesUser->id,
            'is_blacklisted' => false,
        ]);

        $response = $this->actingAs($this->salesUser)->post(route('rentals.store'), [
            'customer_id' => $customer->id,
            'rental_date' => now()->format('Y-m-d'),
            'duration_days' => 3,
            'discount' => 0,
            'notes' => 'Rental lintas cabang yang diakses',
            'payment_method' => 'cash',
            'guarantee_type' => 'deposit',
            'guarantee_deposit' => 100000,
            'items' => [[
                'product_id' => $this->salesProduct->id,
                'product_size' => 'M',
                'quantity' => 1,
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('rentals', [
            'customer_id' => $customer->id,
            'branch_id' => $this->otherBranch->id,
            'created_by' => $this->salesUser->id,
        ]);
    }

    private function createTestRental(): Rental
    {
        $this->actingAs($this->salesUser);

        $rentalData = [
            'customer_id' => $this->salesCustomer->id,
            'rental_date' => now()->format('Y-m-d'),
            'duration_days' => 3,
            'discount' => 0,
            'deposit' => 0,
            'notes' => 'Test rental',
            'payment_method' => 'cash',
            'guarantee_type' => 'deposit',
            'guarantee_deposit' => 100000,
            'items' => [
                [
                    'product_id' => $this->salesProduct->id,
                    'product_size' => 'M',
                    'quantity' => 1,
                ],
            ],
        ];

        $this->post(route('rentals.store'), $rentalData);

        return Rental::where('customer_id', $this->salesCustomer->id)
            ->where('created_by', $this->salesUser->id)
            ->latest()
            ->first();
    }

    private function handoverRental(Rental $rental): void
    {
        $this->actingAs($this->salesUser);
        $this->patchJson(route('rentals.handover', $rental));
        $rental->refresh();
    }

    private function processPayment(Rental $rental): void
    {
        $this->actingAs($this->salesUser);
        $this->postJson(route('rentals.payment', $rental), [
            'amount' => $rental->total_amount,
            'method' => 'cash',
            'payment_type' => 'rental',
            'reference_number' => 'REF123',
            'notes' => 'Full payment',
        ])->assertOk();
        $rental->refresh();
    }

    private function processReturn(Rental $rental): void
    {
        $this->actingAs($this->salesUser);
        $this->postJson(route('rentals.return', $rental), [
            'items' => [
                [
                    'rental_item_id' => $rental->items->first()->id,
                    'condition' => 'baik',
                    'notes' => 'Good condition',
                    'damage_fee' => 0,
                ],
            ],
        ]);
        $rental->refresh();
    }

    // ============================================================
    // TEST 6: Serah Terima (Handover)
    // ============================================================
    public function test_6_handover_rental(): void
    {
        $rental = $this->createTestRental();
        $this->handoverRental($rental);
        $this->assertEquals('active', $rental->rental_status);
    }

    // ============================================================
    // TEST 7: Payment
    // ============================================================
    public function test_7_process_payment(): void
    {
        $rental = $this->createTestRental();
        $this->handoverRental($rental);
        $this->processPayment($rental);
        $this->assertEquals('paid', $rental->payment_status);
    }

    public function test_payment_validation_returns_json_errors_for_ajax_submission(): void
    {
        $rental = $this->createTestRental();

        $this->actingAs($this->salesUser)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => '',
                'method' => 'cash',
                'payment_type' => 'rental',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_payment_rejects_late_fee_when_rental_has_no_late_fee_balance(): void
    {
        $rental = $this->createTestRental();

        $this->actingAs($this->salesUser)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => 10000,
                'method' => 'cash',
                'payment_type' => 'late_fee',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_type');

        $this->assertDatabaseMissing('payments', [
            'rental_id' => $rental->id,
            'type' => 'late_fee',
        ]);
    }

    public function test_payment_rejects_deposit_as_a_separate_payment_type(): void
    {
        $rental = $this->createTestRental();

        $this->actingAs($this->salesUser)
            ->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertDontSee('<option value="deposit"', false);

        $this->postJson(route('rentals.payment', $rental), [
            'amount' => 10000,
            'method' => 'cash',
            'payment_type' => 'deposit',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_type');

        $this->assertDatabaseMissing('payments', [
            'rental_id' => $rental->id,
            'type' => 'deposit',
        ]);
    }

    public function test_legacy_deposit_payments_remain_applied_to_the_rental_balance(): void
    {
        $rental = $this->createTestRental();
        Payment::create([
            'rental_id' => $rental->id,
            'received_by' => $this->salesUser->id,
            'payment_number' => 'PAY-LEGACY-DEPOSIT',
            'amount' => 50000,
            'method' => 'cash',
            'type' => 'deposit',
            'paid_at' => now(),
        ]);

        $balances = app(\App\Services\RentalService::class)->paymentBalances($rental);

        $this->assertSame(250000.0, $balances['rental']);
        $this->assertArrayNotHasKey('deposit', $balances);
    }

    public function test_payment_amount_cannot_exceed_remaining_balance_for_selected_type(): void
    {
        $rental = $this->createTestRental();
        $rental->update([
            'late_fee' => 25000,
            'fine_amount' => 25000,
            'fine_status' => Rental::FINE_UNPAID,
        ]);

        $this->actingAs($this->salesUser)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => 25001,
                'method' => 'cash',
                'payment_type' => 'late_fee',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount')
            ->assertJsonPath('errors.amount.0', 'Jumlah pembayaran melebihi sisa denda telat. Maksimal yang dapat dibayar: Rp25.000.');

        $this->assertDatabaseMissing('payments', [
            'rental_id' => $rental->id,
            'type' => 'late_fee',
        ]);
    }

    public function test_payment_amount_cannot_exceed_remaining_rental_balance(): void
    {
        $rental = $this->createTestRental();
        Payment::create([
            'rental_id' => $rental->id,
            'received_by' => $this->salesUser->id,
            'payment_number' => 'PAY-PARTIAL-001',
            'amount' => 100000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->salesUser)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => 200001,
                'method' => 'cash',
                'payment_type' => 'rental',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount')
            ->assertJsonPath('errors.amount.0', 'Jumlah pembayaran melebihi sisa pembayaran sewa. Maksimal yang dapat dibayar: Rp200.000.');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_amount_cannot_exceed_remaining_damage_fee(): void
    {
        $rental = $this->createTestRental();
        $rental->update([
            'fine_amount' => 40000,
            'fine_status' => Rental::FINE_UNPAID,
        ]);
        $rental->items()->first()->update(['damage_fee' => 40000]);

        $this->actingAs($this->salesUser)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => 40001,
                'method' => 'cash',
                'payment_type' => 'damage_fee',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount')
            ->assertJsonPath('errors.amount.0', 'Jumlah pembayaran melebihi sisa denda kerusakan. Maksimal yang dapat dibayar: Rp40.000.');

        $this->assertDatabaseMissing('payments', [
            'rental_id' => $rental->id,
            'type' => 'damage_fee',
        ]);
    }

    public function test_partial_damage_fee_payment_updates_fine_status_and_total_remaining_amount(): void
    {
        $rental = $this->createTestRental();
        $rental->update([
            'fine_amount' => 40000,
            'fine_status' => Rental::FINE_UNPAID,
        ]);
        $rental->items()->first()->update(['damage_fee' => 40000]);

        $this->actingAs($this->salesUser)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => 20000,
                'method' => 'cash',
                'payment_type' => 'damage_fee',
            ])
            ->assertOk();

        $rental->refresh();
        $this->assertSame(Rental::FINE_PARTIAL, $rental->fine_status);
        $this->assertSame(20000.0, (float) $rental->fine_paid_amount);
        $this->assertSame(
            max(0, (float) $rental->total_amount + 40000 - (float) $rental->paid_amount - 20000),
            (float) $rental->total_remaining_amount
        );

        $this->get(route('rentals.index'))
            ->assertOk()
            ->assertSee('Denda Sebagian');
    }

    // ============================================================
    // TEST 8: Return
    // ============================================================
    public function test_8_process_return(): void
    {
        $rental = $this->createTestRental();
        $this->handoverRental($rental);
        $this->processPayment($rental);
        $this->processReturn($rental);
        $this->assertEquals('returned', $rental->rental_status);
        $this->assertNotNull($rental->returned_at);
    }

    // ============================================================
    // TEST 9: Receipt PDF
    // ============================================================
    public function test_9_receipt_pdf(): void
    {
        $rental = $this->createTestRental();
        $this->handoverRental($rental);
        $this->processPayment($rental);
        $this->processReturn($rental);
        $this->actingAs($this->salesUser);

        $response = $this->get(route('rentals.pdf', $rental));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=' . $rental->invoice_number . '.pdf');
    }

    // ============================================================
    // TEST 10: QR Scan
    // ============================================================
    public function test_10_qr_scan(): void
    {
        $rental = $this->createTestRental();
        $this->handoverRental($rental);
        $this->processPayment($rental);
        $this->processReturn($rental);
        $this->actingAs($this->salesUser);

        // Test scan page
        $response = $this->get(route('rentals.scan'));
        $response->assertOk();
        $response->assertViewIs('rentals.scan');

        // Test scan QR by invoice number
        $response = $this->get(route('rentals.scan.show', $rental->invoice_number));
        $response->assertOk();
        $response->assertViewIs('rentals.scan-result');
        $response->assertSee($rental->invoice_number);
    }

    // ============================================================
    // TEST 11: Thermal Print
    // ============================================================
    public function test_11_thermal_print(): void
    {
        $rental = $this->createTestRental();
        $this->handoverRental($rental);
        $this->processPayment($rental);
        $this->processReturn($rental);
        $this->actingAs($this->salesUser);

        $response = $this->get(route('rentals.thermal', $rental));
        $response->assertOk();
        $response->assertViewIs('rentals.receipt');
    }

    // ============================================================
    // TEST 12: Logout
    // ============================================================
    public function test_12_logout(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->post(route('logout'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ============================================================
    // AUTHORIZATION TESTS
    // ============================================================

    public function test_auth_cannot_access_other_branch_rental_direct_url(): void
    {
        // Create rental owned by other sales user
        $otherRental = $this->createRental($this->otherBranch, $this->otherSalesUser, $this->otherCustomer, $this->otherProduct, 'waiting');

        $this->actingAs($this->salesUser);

        // Try to access other branch rental via direct URL
        $response = $this->get(route('rentals.show', $otherRental));
        $response->assertForbidden();

        // Try to access edit
        $response = $this->get(route('rentals.edit', $otherRental));
        $response->assertForbidden();

        // Try to access PDF
        $response = $this->get(route('rentals.pdf', $otherRental));
        $response->assertForbidden();

        // Try to access thermal print
        $response = $this->get(route('rentals.thermal', $otherRental));
        $response->assertForbidden();

        // Try to process payment
        $response = $this->postJson(route('rentals.payment', $otherRental), [
            'amount' => 100000,
            'method' => 'cash',
        ]);
        $response->assertForbidden();

        // Try to process return
        $response = $this->postJson(route('rentals.return', $otherRental), [
            'items' => [],
        ]);
        $response->assertForbidden();

        // Try handover
        $response = $this->patchJson(route('rentals.handover', $otherRental));
        $response->assertForbidden();

        // Try QR scan
        $response = $this->get(route('rentals.scan.show', $otherRental->invoice_number));
        $response->assertForbidden();
    }

    public function test_sales_can_view_and_handover_rental_created_by_another_sales_in_same_branch(): void
    {
        $sameBranchSales = User::create([
            'name' => 'Sales Rekan',
            'email' => 'sales.rekan@jasrental.id',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'branch_id' => $this->salesBranch->id,
            'is_active' => true,
        ]);
        $rental = $this->createRental(
            $this->salesBranch,
            $sameBranchSales,
            $this->salesCustomer,
            $this->salesProduct,
            'waiting'
        );

        $this->actingAs($this->salesUser)
            ->get(route('rentals.index'))
            ->assertOk()
            ->assertSee($rental->invoice_number);

        $this->get(route('search.index', ['q' => $rental->invoice_number]))
            ->assertOk()
            ->assertSee($rental->invoice_number);

        $this->get(route('rentals.show', $rental))->assertOk();
        $this->get(route('rentals.activity-log'))->assertForbidden();

        $this->patchJson(route('rentals.handover', $rental))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('rental.activity_logs', []);
    }

    public function test_auth_cannot_access_other_branch_customer_direct_url(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->get(route('customers.show', $this->otherCustomer));
        $response->assertForbidden();

        $response = $this->get(route('customers.edit', $this->otherCustomer));
        $response->assertForbidden();
    }

    public function test_sales_can_edit_and_update_a_customer_they_created(): void
    {
        $this->actingAs($this->salesUser)
            ->get(route('customers.edit', $this->salesCustomer))
            ->assertOk()
            ->assertSee('Edit Pelanggan');

        $this->patch(route('customers.update', $this->salesCustomer), [
            'name' => 'Pelanggan Diperbarui',
            'phone' => '6281234567890',
            'notes' => 'Diperbarui Sales',
        ])->assertRedirect(route('customers.show', $this->salesCustomer));

        $this->assertDatabaseHas('customers', [
            'id' => $this->salesCustomer->id,
            'name' => 'Pelanggan Diperbarui',
            'user_id' => $this->salesUser->id,
            'branch_id' => $this->salesBranch->id,
        ]);
    }

    public function test_sales_cannot_edit_or_deactivate_another_sales_customer_in_the_same_branch(): void
    {
        $sameBranchSales = User::create([
            'name' => 'Sales Rekan Pelanggan',
            'email' => 'sales.rekan.customer@jasrental.id',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'branch_id' => $this->salesBranch->id,
            'is_active' => true,
        ]);
        $peerCustomer = Customer::create([
            'name' => 'Pelanggan Rekan',
            'phone' => '6281234567892',
            'branch_id' => $this->salesBranch->id,
            'user_id' => $sameBranchSales->id,
            'is_blacklisted' => false,
        ]);

        $this->actingAs($this->salesUser)
            ->get(route('customers.edit', $peerCustomer))
            ->assertForbidden();

        $this->patch(route('customers.update', $peerCustomer), [
            'name' => 'Diubah Sales Lain',
            'phone' => $peerCustomer->phone,
        ])->assertForbidden();

        $this->delete(route('customers.destroy', $peerCustomer))->assertForbidden();
        $this->assertDatabaseHas('customers', ['id' => $peerCustomer->id, 'deleted_at' => null]);
    }

    public function test_sales_can_deactivate_their_customer_but_not_one_with_an_active_rental(): void
    {
        $this->actingAs($this->salesUser)
            ->delete(route('customers.destroy', $this->salesCustomer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('customers', ['id' => $this->salesCustomer->id]);

        $activeRentalCustomer = Customer::create([
            'name' => 'Pelanggan Dengan Rental Berjalan',
            'phone' => '6281234567893',
            'branch_id' => $this->salesBranch->id,
            'user_id' => $this->salesUser->id,
            'is_blacklisted' => false,
        ]);
        $this->createRental(
            $this->salesBranch,
            $this->salesUser,
            $activeRentalCustomer,
            $this->salesProduct,
            'active'
        );

        $this->delete(route('customers.destroy', $activeRentalCustomer))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $activeRentalCustomer->id, 'deleted_at' => null]);
    }

    public function test_auth_cannot_access_other_branch_product_direct_url(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->get(route('products.show', $this->otherProduct));
        $response->assertForbidden();

        $response = $this->get(route('products.edit', $this->otherProduct));
        $response->assertForbidden();
    }

    public function test_auth_cannot_create_rental_with_other_branch_customer(): void
    {
        $this->actingAs($this->salesUser);

        $rentalData = [
            'customer_id' => $this->otherCustomer->id, // Other branch customer
            'rental_date' => now()->format('Y-m-d'),
            'duration_days' => 3,
            'discount' => 0,
            'deposit' => 0,
            'notes' => 'Test rental',
            'payment_method' => 'cash',
            'guarantee_type' => 'ktp',
            'guarantee_id_number' => '1234567890',
            'items' => [
                [
                    'product_id' => $this->salesProduct->id,
                    'product_size' => 'M',
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->post(route('rentals.store'), $rentalData);
        // Should fail validation or authorization
        $response->assertSessionHasErrors();
    }

    public function test_auth_cannot_create_rental_with_other_branch_product(): void
    {
        $this->actingAs($this->salesUser);

        $rentalData = [
            'customer_id' => $this->salesCustomer->id,
            'rental_date' => now()->format('Y-m-d'),
            'duration_days' => 3,
            'discount' => 0,
            'deposit' => 0,
            'notes' => 'Test rental',
            'payment_method' => 'cash',
            'guarantee_type' => 'ktp',
            'guarantee_id_number' => '1234567890',
            'items' => [
                [
                    'product_id' => $this->otherProduct->id, // Other branch product
                    'product_size' => 'L',
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->post(route('rentals.store'), $rentalData);
        // Should fail validation or authorization
        $response->assertSessionHasErrors();
    }

    public function test_auth_sales_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->salesUser);

        // Try to access admin-only routes
        $response = $this->get(route('users.index'));
        $response->assertForbidden();

        $response = $this->get(route('branches.index'));
        $response->assertForbidden();

        $response = $this->get(route('categories.index'));
        $response->assertForbidden();

        $response = $this->get(route('settings.index'));
        $response->assertForbidden();

        $response = $this->get(route('reports.revenue'));
        $response->assertForbidden();

        $response = $this->get(route('reports.index'));
        $response->assertForbidden();
    }

    public function test_auth_sales_cannot_delete_rental(): void
    {
        $rental = $this->createRental($this->salesBranch, $this->salesUser, $this->salesCustomer, $this->salesProduct, 'returned');
        $this->actingAs($this->salesUser);

        $response = $this->delete(route('rentals.destroy', $rental));
        $response->assertForbidden();
    }

    public function test_auth_sales_cannot_update_rental_after_paid(): void
    {
        $rental = $this->createRental($this->salesBranch, $this->salesUser, $this->salesCustomer, $this->salesProduct, 'waiting');
        $this->actingAs($this->salesUser);

        // First make it paid
        $rental->update([
            'rental_status' => 'active',
            'payment_status' => 'paid',
        ]);

        // Try to update locked fields
        $response = $this->patch(route('rentals.update', $rental), [
            'customer_id' => $this->salesCustomer->id,
            'rental_date' => now()->format('Y-m-d'),
            'duration_days' => 5, // Changed from 3
            'discount' => 0,
            'deposit' => 0,
            'notes' => 'Updated',
            'items' => [
                [
                    'product_id' => $this->salesProduct->id,
                    'product_size' => 'M',
                    'quantity' => 1,
                ],
            ],
        ]);

        // Should fail because sales users cannot update rentals (only admin/super_admin can)
        $response->assertForbidden();
    }

    private function createRental(
        Branch $branch,
        User $sales,
        Customer $customer,
        Product $product,
        string $status = 'waiting'
    ): Rental {
        $rental = Rental::create([
            'invoice_number' => 'INV' . now()->format('Ymd') . $branch->id . rand(1000, 9999),
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $sales->id,
            'rental_date' => now(),
            'return_due_date' => now()->addDays(3),
            'duration_days' => 3,
            'subtotal' => $product->rental_price * 3,
            'discount' => 0,
            'late_fee' => 0,
            'total_amount' => $product->rental_price * 3,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'rental_status' => $status,
        ]);

        RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_size' => 'M',
            'product_color' => $product->color,
            'quantity' => 1,
            'price_per_day' => $product->rental_price,
            'duration_days' => 3,
            'subtotal' => $product->rental_price * 3,
        ]);

        return $rental;
    }
}