<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DashboardAndActivityLogScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_dashboard_applies_its_assigned_branch_and_selected_sales(): void
    {
        $branch = $this->makeBranch('Solo');
        $sales = $this->makeUser('sales', $branch);
        $admin = $this->makeUser('admin_toko', $branch);

        $service = Mockery::mock(DashboardService::class);
        $service->shouldReceive('buildPayload')
            ->once()
            ->with(Mockery::on(fn (array $filters) =>
                $filters['branch_ids'] === [$branch->id]
                && (int) $filters['sales_user_id'] === $sales->id
            ))
            ->andReturn(['stats' => [], 'charts' => [], 'widgets' => []]);
        $this->app->instance(DashboardService::class, $service);

        $this->actingAs($admin)
            ->getJson(route('dashboard.data', [
                'branch_id' => $branch->id,
                'sales_user_id' => $sales->id,
            ]))
            ->assertOk();
    }

    public function test_admin_dashboard_can_select_another_active_branch_and_its_sales(): void
    {
        $branchA = $this->makeBranch('Solo');
        $branchB = $this->makeBranch('Jogja');
        $admin = $this->makeUser('admin_toko', $branchA);
        $salesB = $this->makeUser('sales', $branchB);

        $service = Mockery::mock(DashboardService::class);
        $service->shouldReceive('buildPayload')
            ->once()
            ->with(Mockery::on(fn (array $filters) =>
                $filters['branch_ids'] === [$branchB->id]
                && (int) $filters['sales_user_id'] === $salesB->id
            ))
            ->andReturn(['stats' => [], 'charts' => [], 'widgets' => []]);
        $this->app->instance(DashboardService::class, $service);

        $this->actingAs($admin)
            ->getJson(route('dashboard.data', [
                'branch_id' => $branchB->id,
                'sales_user_id' => $salesB->id,
            ]))
            ->assertOk();
    }

    public function test_admin_dashboard_rejects_sales_outside_the_selected_branch(): void
    {
        $branchA = $this->makeBranch('Solo');
        $branchB = $this->makeBranch('Jogja');
        $admin = $this->makeUser('admin_toko', $branchA);
        $salesB = $this->makeUser('sales', $branchB);

        $this->actingAs($admin)
            ->getJson(route('dashboard.data', ['branch_id' => $branchA->id, 'sales_user_id' => $salesB->id]))
            ->assertForbidden();

        $this->getJson(route('dashboard.data', ['sales_user_id' => $salesB->id]))
            ->assertForbidden();
    }

    public function test_admin_reports_are_limited_to_accessible_branches_and_report_menu_is_visible(): void
    {
        $branchA = $this->makeBranch('Solo');
        $branchB = $this->makeBranch('Jogja');
        $salesA = $this->makeUser('sales', $branchA);
        $salesB = $this->makeUser('sales', $branchB);
        $admin = $this->makeUser('admin_toko', $branchA);
        $rentalA = $this->makeRental($branchA, $salesA, 'REPORT-SOLO');
        $rentalB = $this->makeRental($branchB, $salesB, 'REPORT-JOGJA');
        $rentalA->update([
            'fine_amount' => 30000,
            'fine_paid_amount' => 10000,
            'fine_status' => Rental::FINE_PARTIAL,
        ]);
        $rentalB->update([
            'fine_amount' => 80000,
            'fine_paid_amount' => 0,
            'fine_status' => Rental::FINE_UNPAID,
        ]);
        Payment::create([
            'rental_id' => $rentalA->id,
            'received_by' => $salesA->id,
            'payment_number' => 'PAY-REPORT-SOLO',
            'amount' => 100000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
        ]);
        Payment::create([
            'rental_id' => $rentalA->id,
            'received_by' => $salesA->id,
            'payment_number' => 'PAY-FINE-SOLO',
            'amount' => 10000,
            'method' => 'cash',
            'type' => 'late_fee',
            'paid_at' => now(),
        ]);
        Payment::create([
            'rental_id' => $rentalB->id,
            'received_by' => $salesB->id,
            'payment_number' => 'PAY-REPORT-JOGJA',
            'amount' => 200000,
            'method' => 'cash',
            'type' => 'rental',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporan Keuangan')
            ->assertSee('Pemasukan Bersih')
            ->assertSee('Pembayaran Sewa')
            ->assertSee('Denda Diterima')
            ->assertSee('Denda Belum Lunas')
            ->assertSee('Denda')
            ->assertDontSee('Kerusakan')
            ->assertSee('Solo')
            ->assertDontSee('Jogja')
            ->assertSee('REPORT-SOLO')
            ->assertDontSee('REPORT-JOGJA')
            ->assertViewHas('summary', fn (array $summary) =>
                (float) $summary['total_amount'] === 110000.0
                && (float) $summary['rental_received'] === 100000.0
                && (float) $summary['fine_received'] === 10000.0
                && $summary['outstanding_fines'] === 20000.0
            );

        $this->get(route('reports.transactions'))
            ->assertOk()
            ->assertSee('REPORT-SOLO')
            ->assertDontSee('REPORT-JOGJA')
            ->assertSee('Laporan')
            ->assertDontSee('Log Keseluruhan');

        $this->get(route('reports.transactions', ['branch_id' => $branchB->id]))
            ->assertForbidden();

        $superAdmin = $this->makeUser('super_admin');
        $this->actingAs($superAdmin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Semua Cabang')
            ->assertSee('Solo')
            ->assertSee('Jogja')
            ->assertSee('Laporan Keuangan')
            ->assertViewHas('summary', fn (array $summary) =>
                (float) $summary['total_amount'] === 310000.0
                && $summary['outstanding_fines'] === 100000.0
            );

        $this->get(route('reports.transactions'))
            ->assertOk()
            ->assertSee('REPORT-SOLO')
            ->assertSee('REPORT-JOGJA');
    }

    public function test_rental_activity_logs_are_exclusive_to_super_admin(): void
    {
        $branch = $this->makeBranch('Solo');
        $otherBranch = $this->makeBranch('Jogja');
        $sales = $this->makeUser('sales', $branch);
        $otherSales = $this->makeUser('sales', $otherBranch);
        $ownRental = $this->makeRental($branch, $sales, 'OWN-001');
        $otherRental = $this->makeRental($otherBranch, $otherSales, 'OTHER-001');
        $this->makeRentalLog($ownRental, $sales, $branch, 'Aktivitas milik Sales ini');
        $this->makeRentalLog($otherRental, $otherSales, $otherBranch, 'Aktivitas milik Sales lain');

        $this->actingAs($sales)
            ->get(route('rentals.activity-log'))
            ->assertForbidden();

        $this->get(route('reports.activity-log'))->assertForbidden();
    }

    public function test_admin_toko_cannot_access_activity_log_but_super_admin_can_filter_it(): void
    {
        $branchA = $this->makeBranch('Solo');
        $branchB = $this->makeBranch('Jogja');
        $admin = $this->makeUser('admin_toko', $branchA);
        $sales = $this->makeUser('sales', $branchA);
        $salesB = $this->makeUser('sales', $branchB);
        $rental = $this->makeRental($branchA, $sales, 'FILTER-001');
        $rentalB = $this->makeRental($branchB, $salesB, 'FILTER-002');
        $this->makeRentalLog($rental, $sales, $branchA, 'Rental dibuat', 'create_rental');
        $this->makeRentalLog($rental, $admin, $branchA, 'Rental diserahkan', 'handover_rental');
        $this->makeRentalLog($rentalB, $salesB, $branchB, 'Rental cabang Jogja', 'create_rental');

        $this->actingAs($admin)
            ->get(route('reports.activity-log', ['branch_id' => $branchB->id]))
            ->assertForbidden();
        $this->get(route('rentals.activity-log'))->assertForbidden();

        $superAdmin = $this->makeUser('super_admin');
        $this->actingAs($superAdmin)
            ->get(route('reports.activity-log', ['tab' => 'rental', 'action' => 'Buat Sewa']))
            ->assertOk()
            ->assertSee('Rental dibuat')
            ->assertDontSee('Rental diserahkan');
    }

    private function makeBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'code' => Str::upper(Str::random(8)),
            'address' => 'Alamat test',
        ]);
    }

    private function makeUser(string $role, ?Branch $branch = null): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' Test',
            'email' => Str::uuid() . '@example.test',
            'role' => $role,
            'branch_id' => $branch?->id,
            'branch_access_type' => $role === 'super_admin' ? 'all' : 'specific',
            'is_active' => true,
        ]);
    }

    private function makeRental(Branch $branch, User $sales, string $invoice): Rental
    {
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Customer ' . $invoice,
            'phone' => '08' . Str::random(10), // Unique phone per rental
        ]);

        return Rental::create([
            'invoice_number' => $invoice,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $sales->id,
            'rental_date' => today(),
            'return_due_date' => today()->addDay(),
            'duration_days' => 1,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'rental_status' => Rental::STATUS_WAITING,
        ]);
    }

    private function makeRentalLog(Rental $rental, User $actor, Branch $actorBranch, string $description, string $action = 'create_rental'): void
    {
        ActivityLog::withoutEvents(fn () => ActivityLog::create([
            'user_id' => $actor->id,
            'branch_id' => $actorBranch->id,
            'action' => $action,
            'model_type' => Rental::class,
            'model_id' => $rental->id,
            'description' => $description,
        ]));
    }
}
