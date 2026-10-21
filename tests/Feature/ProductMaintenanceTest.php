<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStockMaintenance;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mekanisme produk rusak/hilang -> menu Maintenance.
 *
 * Alur yang diuji:
 *  1. Pengembalian dengan kondisi rusak/hilang memindahkan stok ke kolom
 *     product_sizes.stock_maintenance / stock_lost dan membuat record
 *     product_stock_maintenances.
 *  2. Menu Maintenance (restore / dispose) menyelesaikan record tersebut.
 *  3. Pembatalan pengembalian (cancel-return) mengembalikan stok.
 */
class ProductMaintenanceTest extends TestCase
{
    use RefreshDatabase;

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
            'name' => 'Cabang Maintenance',
            'code' => 'MNT01',
            'address' => 'Jl. Test No. 1',
            'phone' => '021-111111',
            'email' => 'maintenance@jasrental.id',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Maintenance',
            'slug' => 'kategori-maintenance',
            'is_active' => true,
        ]);

        $this->sales = User::create([
            'name' => 'Sales Maintenance',
            'email' => 'sales.maintenance@jasrental.id',
            'password' => bcrypt('password'),
            'phone' => '0811111111111',
            'role' => 'sales',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Maintenance',
            'email' => 'admin.maintenance@jasrental.id',
            'password' => bcrypt('password'),
            'phone' => '0811111111112',
            'role' => 'admin_toko',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Customer Maintenance',
            'phone' => '6281111111111',
            'branch_id' => $this->branch->id,
            'user_id' => $this->sales->id,
            'is_blacklisted' => false,
        ]);
    }

    private function makeProduct(int $total, bool $withSizeRow = true): Product
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Jas Maintenance ' . uniqid(),
            'code' => 'MNT-' . strtoupper(uniqid()),
            'color' => 'Hitam',
            'brand' => 'Test',
            'rental_price' => 100000,
            'deposit_price' => 0,
            'points' => 3,
            'condition' => 'excellent',
            'status' => 'available',
            'branch_id' => $this->branch->id,
            'stock_total' => $total,
            'stock_available' => $total,
            'size' => null,
        ]);
        $product->branches()->attach($this->branch->id);

        if ($withSizeRow) {
            $product->sizes()->create([
                'size' => 'M',
                'stock_total' => $total,
                'stock_available' => $total,
            ]);
        }

        return $product;
    }

    private function makeRental(array $overrides = []): Rental
    {
        $this->invoiceSeq++;

        return Rental::create(array_merge([
            'invoice_number' => 'INV' . now()->format('Ymd') . '99' . str_pad((string) $this->invoiceSeq, 4, '0', STR_PAD_LEFT),
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

    /**
     * Menambah item SEKALIGUS memotong stok seperti layaknya RentalService::createRental().
     */
    private function addItem(Rental $rental, Product $product, int $quantity, string $size = 'M'): RentalItem
    {
        $item = $rental->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_size' => $size,
            'quantity' => $quantity,
            'price_per_day' => 100000,
            'duration_days' => $rental->duration_days,
            'subtotal' => 100000 * $quantity,
        ]);

        $product->decrementSizeStock($size, $quantity);

        return $item;
    }

    private function payFull(Rental $rental): void
    {
        Payment::create([
            'rental_id' => $rental->id,
            'received_by' => $this->sales->id,
            'payment_number' => 'PAY-' . strtoupper(uniqid()),
            'amount' => $rental->total_amount,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
        ]);

        $rental->update(['payment_status' => Rental::PAYMENT_PAID]);
    }

    private function returnItems(Rental $rental, array $items): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('rentals.return', $rental), ['items' => $items])
            ->assertStatus(302);
    }

    private function sizeRow(Product $product, string $size = 'M'): \App\Models\ProductSize
    {
        return $product->sizes()->where('size', $size)->firstOrFail();
    }

    // ─── 1. RETURN RUSAK -> MASUK MAINTENANCE ─────────────────────────

    public function test_return_damaged_item_moves_stock_to_maintenance_and_creates_record(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);

        $this->assertSame(4, (int) $this->sizeRow($product)->stock_available);

        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_berat',
            'damage_fee' => 25000,
            'notes' => 'Sobek di lengan',
        ]]);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(4, (int) $size->stock_available, 'Unit rusak tidak boleh kembali ke available.');
        $this->assertSame(1, (int) $size->stock_maintenance);
        $this->assertSame(0, (int) $size->stock_lost);
        $this->assertSame(5, (int) $size->stock_total, 'Kerusakan tidak mengurangi total stok.');
        $this->assertSame(4, (int) $product->fresh()->stock_available);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->first();
        $this->assertNotNull($record, 'Record maintenance harus dibuat saat pengembalian rusak.');
        $this->assertSame('maintenance', $record->status);
        $this->assertSame('rusak_berat', $record->condition);
        $this->assertSame(1, (int) $record->quantity);
        $this->assertSame(25000.0, (float) $record->damage_fee);
        $this->assertNotNull($record->product_size_id, 'Record wajib terhubung ke baris product_sizes.');
    }

    public function test_restore_moves_maintenance_unit_back_to_available(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_ringan',
            'damage_fee' => 10000,
        ]]);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('products.maintenance.restore', $record))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(0, (int) $size->stock_maintenance);
        $this->assertSame(5, (int) $size->stock_available);
        $this->assertSame(5, (int) $size->stock_total);

        $record->refresh();
        $this->assertSame('restored', $record->status);
        $this->assertSame($this->admin->id, (int) $record->resolved_by);
        $this->assertNotNull($record->resolved_at);
    }

    public function test_dispose_reduces_total_stock_once_for_damaged_unit(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_berat',
            'damage_fee' => 30000,
        ]]);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('products.maintenance.dispose', $record))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(0, (int) $size->stock_maintenance, 'Record disposed harus mengosongkan kolom maintenance.');
        $this->assertSame(4, (int) $size->stock_total, 'Dispose mengurangi total stok tepat satu kali.');
        $this->assertSame(4, (int) $size->stock_available, 'Available tidak berubah: unit memang tidak pernah kembali.');

        $this->assertSame('disposed', $record->fresh()->status);
    }

    // ─── 2. RETURN HILANG ─────────────────────────────────────────────

    public function test_return_lost_unit_reduces_total_and_counts_lost(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);

        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'hilang',
            'damage_fee' => 50000,
        ]]);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(4, (int) $size->stock_total, 'Barang hilang mengurangi total stok saat pengembalian.');
        $this->assertSame(4, (int) $size->stock_available, 'Available tidak ditambah (unit hilang tidak kembali).');
        $this->assertSame(1, (int) $size->stock_lost);
        $this->assertSame(0, (int) $size->stock_maintenance);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('hilang', $record->condition);
    }

    public function test_dispose_of_lost_unit_does_not_reduce_total_stock_twice(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'hilang',
            'damage_fee' => 50000,
        ]]);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->firstOrFail();
        $this->assertSame(4, (int) $this->sizeRow($product)->stock_total);

        $this->actingAs($this->admin)
            ->delete(route('products.maintenance.dispose', $record))
            ->assertStatus(302);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame('disposed', $record->fresh()->status);
        $this->assertSame(4, (int) $size->stock_total, 'Total stok sudah berkurang saat return; dispose tidak boleh mengurangi lagi.');
        $this->assertSame(4, (int) $size->stock_available, 'Unit baik lain tidak boleh ikut hilang.');
        $this->assertSame(0, (int) $size->stock_lost, 'Setelah record ditutup, tidak ada unit hilang yang masih tertunda.');
    }

    public function test_restore_of_lost_unit_restores_inventory_without_phantom_stock(): void
    {
        // Total 10; R1 menyewa 4 (tersedia 6).
        $product = $this->makeProduct(10);
        $r1 = $this->makeRental();
        $itemLost = $this->addItem($r1, $product, 2);
        $itemGood = $this->addItem($r1, $product, 2);
        $this->payFull($r1);
        $this->assertSame(6, (int) $this->sizeRow($product)->stock_available);

        // Return: 2 hilang, 2 baik.
        $this->returnItems($r1, [
            ['rental_item_id' => $itemLost->id, 'condition' => 'hilang', 'damage_fee' => 50000],
            ['rental_item_id' => $itemGood->id, 'condition' => 'baik', 'damage_fee' => 0],
        ]);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(8, (int) $size->stock_total);
        $this->assertSame(8, (int) $size->stock_available);
        $this->assertSame(2, (int) $size->stock_lost);

        // R2 masih menyewa 1 unit.
        $r2 = $this->makeRental();
        $this->addItem($r2, $product, 1);
        $size = $this->sizeRow($product->fresh());
        $this->assertSame(7, (int) $size->stock_available);

        $record = ProductStockMaintenance::where('rental_item_id', $itemLost->id)->firstOrFail();

        // Barang hilang ditemukan kembali -> restore.
        $this->actingAs($this->admin)
            ->patch(route('products.maintenance.restore', $record))
            ->assertStatus(302);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(10, (int) $size->stock_total, 'Unit yang ditemukan kembali masuk ke total stok.');
        $this->assertSame(0, (int) $size->stock_lost, 'Unit tidak lagi tertandai hilang.');
        $this->assertSame(
            9,
            (int) $size->stock_available,
            'Tersedia = 10 unit - 1 yang masih disewa R2. Tidak boleh ada stok fiktif.'
        );
        $this->assertSame('restored', $record->fresh()->status);
    }

    // ─── 3. CANCEL RETURN ─────────────────────────────────────────────

    public function test_cancel_return_reverts_maintenance_stock_and_return_state(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_berat',
            'damage_fee' => 25000,
        ]]);

        $this->assertSame(1, (int) $this->sizeRow($product)->stock_maintenance);

        $this->actingAs($this->admin)
            ->patch(route('rentals.cancel-return', $rental))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $rental->refresh();
        $this->assertSame(Rental::STATUS_ACTIVE, $rental->rental_status);
        $this->assertNull($rental->returned_at);
        $this->assertNull($rental->actual_return_date);
        $this->assertNotNull($rental->overdue_days, 'Kolom overdue_days NOT NULL tidak boleh diset null.');
        $this->assertSame(0, (int) $rental->overdue_days);
        $this->assertSame(0.0, (float) $rental->late_fee, 'Denda keterlambatan batal bersama pengembaliannya.');
        $this->assertSame(0.0, (float) $rental->fine_amount, 'Denda kerusakan batal bersama pengembaliannya.');
        $this->assertSame(Rental::FINE_NONE, $rental->fine_status);

        $this->assertFalse((bool) $item->fresh()->is_returned);
        $this->assertSame(0, (int) $this->sizeRow($product)->stock_maintenance, 'Stok maintenance harus kembali.');
        $this->assertSame(4, (int) $this->sizeRow($product)->stock_available);
        $this->assertSame(
            0,
            ProductStockMaintenance::where('rental_item_id', $item->id)->count(),
            'Record maintenance harus ikut dibatalkan.'
        );
    }

    public function test_cancel_return_of_lost_unit_restores_inventory_count(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'hilang',
            'damage_fee' => 50000,
        ]]);

        $this->assertSame(4, (int) $this->sizeRow($product)->stock_total);

        $this->actingAs($this->admin)
            ->patch(route('rentals.cancel-return', $rental))
            ->assertStatus(302);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(5, (int) $size->stock_total, 'Pembatalan mengembalikan unit hilang ke total stok.');
        $this->assertSame(0, (int) $size->stock_lost);
        $this->assertFalse((bool) $item->fresh()->is_returned);
    }

    public function test_cancel_return_removes_completed_rental_history(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'baik',
            'damage_fee' => 0,
        ]]);

        $this->assertNotNull(
            $rental->fresh()->history()->first(),
            'Rental selesai (lunas, tanpa denda) harus tercatat di riwayat.'
        );

        $this->actingAs($this->admin)
            ->patch(route('rentals.cancel-return', $rental))
            ->assertStatus(302);

        $this->assertNull(
            $rental->fresh()->history()->first(),
            'Rental yang pengembaliannya dibatalkan tidak boleh tetap ada di riwayat.'
        );
    }

    // ─── 4. GUARD / EDGE CASES ────────────────────────────────────────

    public function test_return_damaged_item_for_product_without_size_row_does_not_crash(): void
    {
        $product = $this->makeProduct(5, withSizeRow: false);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);

        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_ringan',
            'damage_fee' => 10000,
        ]]);

        $this->assertSame(
            0,
            ProductStockMaintenance::where('rental_item_id', $item->id)->count(),
            'Tidak boleh membuat record tanpa baris product_sizes.'
        );

        $product->refresh();
        $this->assertSame(
            4,
            (int) $product->stock_available,
            'Unit tidak kembali tersedia sampai ukurannya tercatat (konservatif).'
        );
    }

    public function test_restore_cannot_be_run_twice(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_ringan',
            'damage_fee' => 10000,
        ]]);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('products.maintenance.restore', $record))
            ->assertStatus(302);
        $this->assertSame(5, (int) $this->sizeRow($product->fresh())->stock_available);

        // Submit kedua (double klik / tab ganda) tidak boleh menambah stok lagi.
        $this->actingAs($this->admin)
            ->patch(route('products.maintenance.restore', $record))
            ->assertStatus(422);

        $this->assertSame(5, (int) $this->sizeRow($product->fresh())->stock_available);
        $this->assertSame(0, (int) $this->sizeRow($product)->stock_maintenance);
    }

    public function test_restore_still_works_when_product_was_soft_deleted(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_ringan',
            'damage_fee' => 10000,
        ]]);

        $record = ProductStockMaintenance::where('rental_item_id', $item->id)->firstOrFail();

        $product->delete();

        $this->actingAs($this->admin)
            ->patch(route('products.maintenance.restore', $record))
            ->assertStatus(302);

        $record->refresh();
        $this->assertSame('restored', $record->status, 'Record tidak boleh macet hanya karena produk di-soft-delete.');
        $this->assertSame(0, (int) $this->sizeRow($product)->stock_maintenance);
    }

    public function test_confirm_return_ajax_requires_damage_fee_for_lost_condition(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);

        $this->actingAs($this->admin)
            ->patchJson(route('rentals.confirm-return-ajax', $rental), [
                'payment_status' => 'paid',
                'items' => [[
                    'id' => $item->id,
                    'condition' => 'hilang',
                    'notes' => '',
                    'damage_fee' => 0,
                ]],
            ])
            ->assertStatus(422);

        $this->assertFalse((bool) $item->fresh()->is_returned, 'Barang hilang tanpa denda tidak boleh diproses.');
    }

    public function test_return_route_normalizes_lost_condition_instead_of_returning_it_as_good(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);

        $this->actingAs($this->admin)
            ->postJson(route('rentals.return', $rental), [
                'items' => [[
                    'rental_item_id' => $item->id,
                    'condition' => 'lost',
                    'damage_fee' => 50000,
                ]],
            ])
            ->assertStatus(302);

        $size = $this->sizeRow($product->fresh());
        $this->assertSame(
            4,
            (int) $size->stock_total,
            "Kondisi 'lost' harus diperlakukan sebagai hilang, bukan dikembalikan ke stok."
        );
        $this->assertSame(1, (int) $size->stock_lost);
        $this->assertSame(
            'hilang',
            ProductStockMaintenance::where('rental_item_id', $item->id)->value('condition')
        );
    }

    // ─── 5. OVERDUE ───────────────────────────────────────────────────

    public function test_return_stores_overdue_days_used_for_late_fee(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental(['return_due_date' => now()->subDays(2)->toDateString()]);
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);

        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'baik',
            'damage_fee' => 0,
        ]]);

        $rental->refresh();
        $this->assertSame(2, (int) $rental->overdue_days, 'overdue_days harus disimpan saat pengembalian terlambat.');
        $this->assertGreaterThan(0, (float) $rental->late_fee);
    }

    // ─── 6. MENU MAINTENANCE ────────────────────────────────────────────

    public function test_maintenance_menu_lists_open_record_and_is_admin_only(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'rusak_berat',
            'damage_fee' => 25000,
            'notes' => 'Sobek di lengan',
        ]]);

        $page = $this->actingAs($this->admin)
            ->get(route('products.maintenance'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Rusak Berat');

        $this->assertSame(
            1,
            substr_count($page->getContent(), 'Rusak Berat'),
            'Label kondisi tidak boleh tampil lebih dari sekali.'
        );
        $this->assertStringContainsString(
            'Sobek di lengan',
            $page->getContent(),
            'Catatan pengembalian tetap ditampilkan pada kolom Catatan.'
        );

        $this->actingAs($this->sales)
            ->get(route('products.maintenance'))
            ->assertForbidden();
    }

    public function test_condition_column_hides_note_that_only_repeats_the_condition(): void
    {
        $product = $this->makeProduct(5);
        $rental = $this->makeRental();
        $item = $this->addItem($rental, $product, 1);
        $this->payFull($rental);
        $this->returnItems($rental, [[
            'rental_item_id' => $item->id,
            'condition' => 'hilang',
            'damage_fee' => 50000,
            'notes' => 'hilang',
        ]]);

        $content = $this->actingAs($this->admin)
            ->get(route('products.maintenance'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($content, 'Hilang'),
            'Catatan yang hanya mengulang label kondisi harus disembunyikan (badge "Hilang" tampil sekali).'
        );
    }
}
