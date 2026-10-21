<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Rental;
use App\Models\User;
use App\Services\RentalService;
use Tests\TestCase;

class ProductPointsTest extends TestCase
{
    protected Branch $branch;
    protected Category $category;
    protected User $sales;
    protected User $admin;
    protected Customer $customer;
    protected int $invoiceSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->branch = Branch::create([
            'name' => 'Cabang Poin',
            'code' => 'PNT01',
            'address' => 'Jl. Test No. 1',
            'phone' => '021-111111',
            'email' => 'poin@jasrental.id',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Poin',
            'slug' => 'kategori-poin',
            'is_active' => true,
        ]);

        $this->sales = User::create([
            'name' => 'Sales Poin',
            'email' => 'sales.poin@jasrental.id',
            'password' => bcrypt('password'),
            'phone' => '0811111111111',
            'role' => 'sales',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Poin',
            'email' => 'admin.poin@jasrental.id',
            'password' => bcrypt('password'),
            'phone' => '0811111111112',
            'role' => 'admin_toko',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Customer Poin',
            'phone' => '6281111111111',
            'branch_id' => $this->branch->id,
            'user_id' => $this->sales->id,
            'is_blacklisted' => false,
        ]);
    }

    private function makeProduct(int $points, string $suffix): Product
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => "Jas Poin {$suffix}",
            'code' => 'PTS-' . strtoupper($suffix) . '-' . uniqid(),
            'color' => 'Hitam',
            'brand' => 'Test',
            'rental_price' => 100000,
            'deposit_price' => 0,
            'points' => $points,
            'condition' => 'excellent',
            'status' => 'available',
            'branch_id' => $this->branch->id,
            'stock_total' => 10,
            'stock_available' => 10,
            'size' => null,
        ]);
        $product->branches()->attach($this->branch->id);

        return $product;
    }

    private function makeRental(array $overrides = []): Rental
    {
        $this->invoiceSeq++;

        return Rental::create(array_merge([
            'invoice_number' => 'INV' . now()->format('Ymd') . '01' . str_pad((string) $this->invoiceSeq, 4, '0', STR_PAD_LEFT),
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->sales->id,
            'rental_date' => now()->toDateString(),
            'return_due_date' => now()->addDay()->toDateString(),
            'duration_days' => 1,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'payment_status' => Rental::PAYMENT_UNPAID,
            'payment_method' => 'cash',
            'rental_status' => Rental::STATUS_ACTIVE,
        ], $overrides));
    }

    private function addItem(Rental $rental, Product $product, int $quantity): void
    {
        $rental->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_size' => 'M',
            'quantity' => $quantity,
            'price_per_day' => 100000,
            'duration_days' => $rental->duration_days,
            'subtotal' => 100000 * $quantity,
        ]);
    }

    // ─── VALIDASI FORM PRODUK ─────────────────────────────────

    public function test_product_store_requires_points(): void
    {
        $payload = [
            'category_id' => $this->category->id,
            'name' => 'Jas Tanpa Poin',
            'rental_price' => 50000,
            'condition' => 'good',
            'status' => 'available',
            'sizes' => ['M' => ['stock_total' => 2, 'stock_available' => 2]],
        ];

        $this->actingAs($this->admin)
            ->post(route('products.store'), $payload)
            ->assertSessionHasErrors('points');

        $this->assertDatabaseMissing('products', ['name' => 'Jas Tanpa Poin']);
    }

    public function test_product_update_requires_and_persists_points(): void
    {
        $product = $this->makeProduct(3, 'edit');

        $payload = [
            'category_id' => $this->category->id,
            'name' => $product->name,
            'rental_price' => $product->rental_price,
            'condition' => $product->condition,
        ];

        // Tanpa poin -> gagal validasi
        $this->actingAs($this->admin)
            ->from(route('products.edit', $product))
            ->put(route('products.update', $product), $payload)
            ->assertRedirect(route('products.edit', $product))
            ->assertSessionHasErrors('points');

        // Poin negatif -> gagal validasi
        $this->actingAs($this->admin)
            ->put(route('products.update', $product), $payload + ['points' => -1])
            ->assertSessionHasErrors('points');

        // Dengan poin -> tersimpan
        $this->actingAs($this->admin)
            ->put(route('products.update', $product), $payload + ['points' => 7])
            ->assertRedirect(route('products.show', $product));

        $this->assertSame(7, (int) $product->fresh()->points);
    }

    // ─── PERHITUNGAN POIN ─────────────────────────────────────

    public function test_serah_points_are_product_points_times_quantity(): void
    {
        $productA = $this->makeProduct(4, 'a');
        $productB = $this->makeProduct(1, 'b');
        $rental = $this->makeRental();
        $this->addItem($rental, $productA, 2);
        $this->addItem($rental, $productB, 3);

        app(RentalService::class)->addSalesPoints($rental, 'serah');

        $this->assertSame(11, (int) $this->sales->fresh()->total_points); // 4×2 + 1×3
        $this->assertTrue((bool) $rental->fresh()->points_awarded_serah);

        // Guard: pemberian kedua tidak menambah poin lagi
        app(RentalService::class)->addSalesPoints($rental->fresh(), 'serah');
        $this->assertSame(11, (int) $this->sales->fresh()->total_points);
    }

    public function test_payment_and_return_routes_award_dynamic_points(): void
    {
        $product = $this->makeProduct(6, 'full');
        $rental = $this->makeRental();
        $this->addItem($rental, $product, 2); // 6×2 = 12 per event

        $this->actingAs($this->sales)
            ->postJson(route('rentals.payment', $rental), [
                'amount' => $rental->total_amount,
                'method' => 'cash',
                'payment_type' => 'rental',
                'reference_number' => 'REF-POIN-1',
                'notes' => 'Pelunasan',
            ])
            ->assertOk();

        $rental->refresh();
        $this->assertSame(12, (int) $this->sales->fresh()->total_points);
        $this->assertTrue((bool) $rental->points_awarded_serah);
        $this->assertFalse((bool) $rental->points_awarded_kembali);

        $this->actingAs($this->sales)
            ->postJson(route('rentals.return', $rental), [
                'items' => [[
                    'rental_item_id' => $rental->items->first()->id,
                    'condition' => 'baik',
                    'damage_fee' => 0,
                ]],
            ]);

        $rental->refresh();
        $this->assertSame(24, (int) $this->sales->fresh()->total_points);
        $this->assertTrue((bool) $rental->points_awarded_kembali);
    }

    public function test_update_status_awards_kembali_points_only_on_return_transition(): void
    {
        $product = $this->makeProduct(5, 'status');
        $rental = $this->makeRental();
        $this->addItem($rental, $product, 1);

        // Pembayaran lunas yang nyata (recalculatePaymentStatus menghitung dari tabel payments)
        $rental->payments()->create([
            'received_by' => $this->sales->id,
            'payment_number' => 'PAY-POIN-' . $this->invoiceSeq,
            'amount' => 100000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
        ]);
        app(RentalService::class)->recalculatePaymentStatus($rental);
        $rental->refresh();
        $this->assertSame(Rental::PAYMENT_PAID, $rental->payment_status);
        // recalculatePaymentStatus juga memberi poin serah (guard mencegah dobel)
        $this->assertSame(5, (int) $this->sales->fresh()->total_points);

        // Transisi ke 'overdue': poin serah masuk (via recalculatePaymentStatus),
        // tetapi poin kembali TIDAK boleh masuk.
        $this->actingAs($this->sales)
            ->patchJson(route('rentals.update-status', $rental), ['rental_status' => 'overdue'])
            ->assertOk();

        $this->assertFalse((bool) $rental->fresh()->points_awarded_kembali);
        $this->assertSame(5, (int) $this->sales->fresh()->total_points);

        // Transisi ke 'returned': poin kembali masuk.
        $this->actingAs($this->sales)
            ->patchJson(route('rentals.update-status', $rental), ['rental_status' => 'returned'])
            ->assertOk();

        $this->assertTrue((bool) $rental->fresh()->points_awarded_kembali);
        $this->assertSame(10, (int) $this->sales->fresh()->total_points);
    }

    // ─── EDGE CASE ────────────────────────────────────────────

    public function test_items_with_soft_deleted_products_count_zero_points(): void
    {
        $counted = $this->makeProduct(5, 'cnt');
        $deleted = $this->makeProduct(100, 'del');
        $rental = $this->makeRental();
        $this->addItem($rental, $counted, 2);
        $this->addItem($rental, $deleted, 1);

        $deleted->delete();

        app(RentalService::class)->addSalesPoints($rental, 'serah');

        $this->assertSame(10, (int) $this->sales->fresh()->total_points); // hanya 5×2
    }

    public function test_zero_point_product_sets_guard_and_points_are_not_retroactive(): void
    {
        $product = $this->makeProduct(0, 'zero');
        $rental = $this->makeRental();
        $this->addItem($rental, $product, 3);

        app(RentalService::class)->addSalesPoints($rental, 'serah');

        $this->assertSame(0, (int) $this->sales->fresh()->total_points);
        $this->assertTrue((bool) $rental->fresh()->points_awarded_serah);

        // Ubah poin produk tidak menambah poin yang sudah terlanjur diberikan
        $product->update(['points' => 100]);
        app(RentalService::class)->addSalesPoints($rental->fresh(), 'serah');
        $this->assertSame(0, (int) $this->sales->fresh()->total_points);
    }

    public function test_points_only_awarded_to_sales_users(): void
    {
        $product = $this->makeProduct(9, 'nonsales');
        $rental = $this->makeRental(['created_by' => $this->admin->id]);
        $this->addItem($rental, $product, 1);

        app(RentalService::class)->addSalesPoints($rental, 'serah');

        $this->assertSame(0, (int) $this->sales->fresh()->total_points);
        $this->assertSame(0, (int) $this->admin->fresh()->total_points);
        $this->assertFalse((bool) $rental->fresh()->points_awarded_serah);
    }

    public function test_points_for_rejects_unknown_type(): void
    {
        $rental = $this->makeRental();

        $this->expectException(\InvalidArgumentException::class);
        app(RentalService::class)->pointsForRental($rental, 'invalid');
    }
}
