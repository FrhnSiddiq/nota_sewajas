<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Rental;
use App\Models\RentalHistory;
use App\Models\RentalItem;
use App\Models\User;
use App\Services\InvoiceDocumentService;
use Tests\TestCase;

/**
 * Invoice A4 disatukan untuk Detail Penyewaan (data live) dan Riwayat
 * (snapshot): view yang sama, format identik, pill status dinamis, QR verifikasi.
 */
class RentalInvoiceDocumentTest extends TestCase
{
    protected $branch;
    protected $branch2;
    protected $superAdmin;
    protected $sales2;
    protected $category;
    protected $product;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedData();
    }

    private function seedData(): void
    {
        $this->branch = Branch::create([
            'name' => 'Invoice Branch',
            'code' => 'INV01',
            'address' => 'Jl. Test 1',
            'phone' => '021-111111',
            'email' => 'inv1@test.id',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::create([
            'name' => 'Invoice Branch 2',
            'code' => 'INV02',
            'address' => 'Jl. Test 2',
            'phone' => '021-222222',
            'email' => 'inv2@test.id',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Invoice Super Admin',
            'email' => 'invoice-superadmin@test.id',
            'password' => bcrypt('password'),
            'phone' => '081100000001',
            'role' => 'super_admin',
            'branch_id' => null,
            'is_active' => true,
        ]);

        $this->sales2 = User::create([
            'name' => 'Invoice Sales 2',
            'email' => 'invoice-sales2@test.id',
            'password' => bcrypt('password'),
            'phone' => '081100000002',
            'role' => 'sales',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Invoice Category',
            'slug' => 'invoice-category',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'code' => 'PRD-INV001',
            'name' => 'Invoice Product',
            'description' => 'Produk tes invoice',
            'color' => 'Hitam',
            'brand' => 'Test Brand',
            'rental_price' => 100000,
            'deposit_price' => 200000,
            'stock_total' => 10,
            'stock_available' => 10,
            'condition' => 'excellent',
            'status' => 'available',
        ]);

        ProductSize::create([
            'product_id' => $this->product->id,
            'size' => 'M',
            'stock_total' => 10,
            'stock_available' => 10,
        ]);

        $this->customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Invoice Customer',
            'phone' => '081234500001',
            'email' => 'invoice-customer@test.id',
            'address' => 'Jl. Pelanggan 1',
            'id_type' => 'ktp',
            'id_number' => '3171000000000001',
        ]);
    }

    private function makeRental(array $overrides = []): Rental
    {
        $rental = Rental::create(array_merge([
            'invoice_number' => 'INV20261015010001',
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
            'notes' => 'Catatan tes invoice',
        ], $overrides));

        RentalItem::create([
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

        return $rental;
    }

    private function makeHistory(Rental $rental, array $overrides = []): RentalHistory
    {
        return RentalHistory::create(array_merge([
            'rental_id' => $rental->id,
            'branch_id' => $rental->branch_id,
            'customer_id' => $rental->customer_id,
            'created_by' => $rental->created_by,
            'returned_by' => $this->superAdmin->id,
            'invoice_number' => $rental->invoice_number,
            'rental_date' => $rental->rental_date,
            'return_due_date' => $rental->return_due_date,
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
            'items_snapshot' => [[
                'rental_item_id' => 1,
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'product_size' => 'M',
                'product_color' => 'Hitam',
                'quantity' => 1,
                'price_per_day' => 100000.0,
                'duration_days' => 3,
                'subtotal' => 300000.0,
                'return_condition' => 'baik',
                'damage_fee' => 0.0,
                'return_notes' => null,
                'is_returned' => true,
                'returned_at' => now()->toIso8601String(),
                'product_snapshot' => [
                    'id' => $this->product->id,
                    'code' => $this->product->code,
                    'name' => $this->product->name,
                    'category' => $this->category->name,
                    'brand' => $this->product->brand,
                    'color' => $this->product->color,
                    'rental_price' => 100000.0,
                    'condition' => $this->product->condition,
                ],
            ]],
            'guarantees_snapshot' => [],
            'payments_snapshot' => [],
            'completed_at' => now(),
        ], $overrides));
    }

    private function renderInvoice(object $history): string
    {
        return view('rental_histories.invoice', [
            'history' => $history,
        ])->render();
    }

    public function test_detail_rental_can_download_invoice_pdf(): void
    {
        $rental = $this->makeRental();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('rentals.invoice', $rental));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=INV20261015010001-invoice.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_format_identical_between_live_rental_and_history(): void
    {
        $rental = $this->makeRental([
            'payment_status' => 'paid',
            'paid_amount' => 300000,
        ]);
        \App\Models\Payment::create([
            'rental_id' => $rental->id,
            'payment_number' => 'PAY-INV-001',
            'amount' => 300000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
            'received_by' => $this->superAdmin->id,
        ]);
        $history = $this->makeHistory($rental, [
            'payments_snapshot' => [[
                'id' => 1,
                'payment_number' => 'PAY-INV-001',
                'amount' => 300000.0,
                'method' => 'cash',
                'method_label' => 'Tunai',
                'type' => 'rental',
                'paid_at' => now()->toIso8601String(),
                'notes' => null,
            ]],
        ])->load(['branch', 'customer', 'createdBy', 'returnedBy']);

        $liveHtml = $this->renderInvoice(app(InvoiceDocumentService::class)->forRental($rental));
        $historyHtml = $this->renderInvoice($history);

        // Struktur dokumen sama persis di dua sumber data.
        $sharedMarkers = [
            'INVOICE PENYEWAAN',
            $rental->invoice_number,
            'Invoice Customer',
            'Invoice Product',
            'Rincian penyewaan',
            'Ringkasan pembayaran',
            'Rincian pembayaran',
            'Verifikasi dokumen',
            'data:image/svg+xml;base64,',
            'Rp 300.000',
        ];
        foreach ($sharedMarkers as $marker) {
            $this->assertStringContainsString($marker, $liveHtml, "Live rental invoice missing: {$marker}");
            $this->assertStringContainsString($marker, $historyHtml, "History invoice missing: {$marker}");
        }
    }

    public function test_status_pill_reflects_real_payment_state(): void
    {
        // Riwayat lunas → SELESAI · LUNAS (pill hijau, tanpa warn).
        $rentalPaid = $this->makeRental(['invoice_number' => 'INV20261015010002']);
        $historyPaid = $this->makeHistory($rentalPaid)
            ->load(['branch', 'customer', 'createdBy', 'returnedBy']);
        $paidHtml = $this->renderInvoice($historyPaid);
        $this->assertStringContainsString('SELESAI · LUNAS', $paidHtml);
        $this->assertStringNotContainsString('pill pill-warn', $paidHtml);

        // Riwayat dengan sisa tagihan → SELESAI · BELUM LUNAS (pill amber).
        // (Bug lama: pill di-hardcode "SELESAI · LUNAS" meski belum lunas.)
        $rentalPartial = $this->makeRental([
            'invoice_number' => 'INV20261015010003',
            'payment_status' => 'partial',
            'paid_amount' => 100000,
        ]);
        $historyPartial = $this->makeHistory($rentalPartial, [
            'payment_status' => 'partial',
            'paid_amount' => 100000,
        ])->load(['branch', 'customer', 'createdBy', 'returnedBy']);
        $partialHtml = $this->renderInvoice($historyPartial);
        $this->assertStringContainsString('SELESAI · BELUM LUNAS', $partialHtml);
        $this->assertStringContainsString('pill pill-warn', $partialHtml);

        // Rental hidup (waiting) → BOOKING · BELUM LUNAS.
        $liveHtml = $this->renderInvoice(app(InvoiceDocumentService::class)->forRental(
            $this->makeRental(['invoice_number' => 'INV20261015010004'])
        ));
        $this->assertStringContainsString('BOOKING · BELUM LUNAS', $liveHtml);
    }

    public function test_history_invoice_download_still_works(): void
    {
        $rental = $this->makeRental(['invoice_number' => 'INV20261015010005']);
        $history = $this->makeHistory($rental);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('rental-histories.invoice', $history));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=INV20261015010005-invoice.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_detail_buttons_render_on_both_pages(): void
    {
        $rental = $this->makeRental(['invoice_number' => 'INV20261015010006']);
        $history = $this->makeHistory($rental);

        // Detail penyewaan: tombol Invoice PDF baru muncul di antara tombol receipt.
        $this->actingAs($this->superAdmin)
            ->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertSee(route('rentals.invoice', $rental), false);

        // Riwayat: tombol Unduh + WA Invoice (dengan normalisasi nomor 62).
        $showHtml = $this->actingAs($this->superAdmin)
            ->get(route('rental-histories.show', $history))
            ->assertOk()
            ->assertSee('WA Invoice')
            ->assertSee('downloadInvoiceAndOpenWa', false)
            ->assertSee('https://wa.me/', false)
            ->assertSee('6281234500001', false)
            ->getContent();
        $this->assertStringContainsString(route('rental-histories.invoice', $history), $showHtml);
    }

    public function test_receipt_qr_points_to_scan_page_with_invoice_number(): void
    {
        $rental = $this->makeRental(['invoice_number' => 'INV20261015010008']);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('rentals.receipt.show', $rental))
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,', false)
            ->getContent();

        preg_match('/data:image\/svg\+xml;base64,([A-Za-z0-9+\/=]+)/', $html, $m);
        $this->assertNotEmpty($m, 'QR SVG tidak ditemukan di halaman receipt');

        // QR struk harus meng-encode URL halaman scan dengan nomor invoice
        // (bukan /rentals/{id} berbasis ID numerik).
        $expected = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(160)
            ->generate(route('rentals.scan.show', ['invoice' => $rental->invoice_number]));

        $this->assertSame($expected, base64_decode($m[1]));
    }

    public function test_invoice_is_forbidden_for_sales_from_another_branch(): void
    {
        $rental = $this->makeRental(['invoice_number' => 'INV20261015010007']);

        $this->actingAs($this->sales2)
            ->get(route('rentals.invoice', $rental))
            ->assertForbidden();
    }
}
