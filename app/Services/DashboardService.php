<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Payment;
use App\Models\ActivityLog;
use App\Models\RentalReturn;
use App\Models\RentalItem;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class DashboardService
{
    public function getDashboardDataForUser(User $user): array
    {
        return match ($user->role) {
            'super_admin' => $this->getSuperAdminDashboardPayload(),
            'admin_toko'  => $this->getScopedDashboardPayload($user),
            'sales'       => $this->getSalesDashboardPayload($user),
            default       => [],
        };
    }

    private function getSuperAdminDashboardPayload(): array
    {
        $filters = [
            'scope_type' => 'super_admin',
            'branch_ids' => null,
            'sales_user_id' => null,
        ];

        return $this->buildPayload($filters);
    }

    private function getScopedDashboardPayload(User $user): array
    {
        $filters = [
            'scope_type' => 'admin',
            'branch_ids' => $user->isSuperAdmin() ? null : $user->accessible_branch_ids,
            'sales_user_id' => null,
        ];

        return $this->buildPayload($filters);
    }

    private function getSalesDashboardPayload(User $user): array
    {
        $filters = [
            'scope_type' => 'sales',
            'branch_ids' => $user->isSuperAdmin() ? null : $user->accessible_branch_ids,
            'sales_user_id' => $user->id,
        ];

        return $this->buildPayload($filters);
    }

    public function buildPayload(array $filters): array
    {
        $tz = 'Asia/Jakarta';
        $today = Carbon::today($tz);
        $now = Carbon::now($tz);

        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();

        $branchIds = Arr::get($filters, 'branch_ids');
        $salesUserId = Arr::get($filters, 'sales_user_id');
        $dateFrom = Arr::get($filters, 'date_from');
        $dateTo = Arr::get($filters, 'date_to');

        $start30 = $dateFrom ? Carbon::parse($dateFrom, $tz)->startOfDay() : $now->copy()->subDays(29)->startOfDay();
        $end30 = $dateTo ? Carbon::parse($dateTo, $tz)->endOfDay() : $now->copy()->endOfDay();

        $chartLabel = $dateFrom && $dateTo
            ? Carbon::parse($dateFrom, $tz)->format('d M') . ' - ' . Carbon::parse($dateTo, $tz)->format('d M Y')
            : '30 Hari';

        $customerQuery = Customer::query();
        $productQuery = Product::query();
        $rentalQuery = Rental::query();
        $paymentQuery = Payment::query();

        if (!empty($branchIds)) {
            $customerQuery->whereIn('branch_id', $branchIds);
            $productQuery->whereIn('branch_id', $branchIds);
            $rentalQuery->whereIn('branch_id', $branchIds);
        }

        if (!is_null($salesUserId)) {
            $customerQuery->where('user_id', $salesUserId);
            $rentalQuery->where('created_by', $salesUserId);
        }

        $applyBranchFilter = function (Builder $q) use ($branchIds) {
            if (!empty($branchIds)) {
                $q->whereIn('branch_id', $branchIds);
            }
        };

        $applyRentalBranchFilter = function (Builder $q) use ($branchIds) {
            if (!empty($branchIds)) {
                $q->whereIn('branch_id', $branchIds);
            }
        };

        // Dashboard statistics (aggregate queries)
        $totalCustomers = (clone $customerQuery)->count();

        // Customer Aktif: customer dengan rental_status active/overdue
        $customerActive = (clone $customerQuery)
            ->whereHas('rentals', function (Builder $q) use ($applyRentalBranchFilter, $salesUserId) {
                $applyRentalBranchFilter($q);
                if (!is_null($salesUserId)) $q->where('created_by', $salesUserId);
                $q->whereIn('rental_status', ['active', 'overdue']);
            })->count();

        $newCustomersToday = (clone $customerQuery)->whereDate('created_at', $today)->count();
        $newCustomersThisMonth = (clone $customerQuery)
            ->whereBetween('created_at', [$startOfMonth->copy()->startOfDay()->toDateString(), $now->copy()->endOfDay()->toDateString()])
            ->count();

        // Products
        $totalProducts = (clone $productQuery)->count();
        $productsActive = (clone $productQuery)->where('status', 'available')->count();
        $productsRented = (clone $productQuery)->where('status', 'rented')->count();
        $productsAvailable = (clone $productQuery)->where('status', 'available')->where('stock_available', '>', 0)->count();
        $productsAlmostEmpty = (clone $productQuery)->where('status', 'available')->whereBetween('stock_available', [1, 3])->count();
        $productsMaintenance = (clone $productQuery)->where('status', 'maintenance')->count();

        // Rentals
        $totalRentals = (clone $rentalQuery)->count();
        $rentalsToday = (clone $rentalQuery)->whereDate('rental_date', $today)->count();
        $rentalsThisWeek = (clone $rentalQuery)->whereBetween('rental_date', [$startOfWeek->toDateString(), $now->toDateString()])->count();
        $rentalsThisMonth = (clone $rentalQuery)->whereBetween('rental_date', [$startOfMonth->toDateString(), $now->toDateString()])->count();
        $rentalsThisYear = (clone $rentalQuery)->whereYear('rental_date', $now->year)->count();

        $rentalActive = (clone $rentalQuery)->whereIn('rental_status', ['active', 'overdue'])->count();
        $rentalWaiting = (clone $rentalQuery)->where('rental_status', 'waiting')->count();
        $rentalLate = (clone $rentalQuery)->where('rental_status', 'overdue')->count();
        $rentalFinished = (clone $rentalQuery)->completed()->count();
        $rentalCancelled = (clone $rentalQuery)->where('rental_status', 'cancelled')->count();

        // Finance: revenue = payments.amount where paid_at within period
        $revenueToday = (clone $paymentQuery)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->whereDate('paid_at', $today)
            ->sum('amount');

        $revenueThisWeek = (clone $paymentQuery)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->whereBetween('paid_at', [$startOfWeek->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->sum('amount');

        $revenueThisMonth = (clone $paymentQuery)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->whereBetween('paid_at', [$startOfMonth->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->sum('amount');

        $revenueThisYear = (clone $paymentQuery)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->whereBetween('paid_at', [$now->copy()->startOfYear(), $now->copy()->endOfYear()])
            ->sum('amount');

        // Invoice counts
        // Invoice Hari Ini: rental yang dibuat hari ini (rental_date)
        $invoiceToday = (clone $rentalQuery)->whereDate('rental_date', $today)->count();

        // Invoice Belum Dibayar: payment_status != paid
        $invoiceUnpaidCount = (clone $rentalQuery)->where('payment_status', '!=', 'paid')->count();

        // Receipt Hari Ini: payment transaction count paid hari ini
        $receiptToday = (clone $paymentQuery)
            ->whereDate('paid_at', $today)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->count();

        // Returns
        $returnedToday = RentalReturn::query()
            ->whereDate('returned_at', $today)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->count();

        // Deposit & Denda
        $depositMasuk = \App\Models\Guarantee::query()
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->where('type', 'deposit')
            ->sum('deposit_amount');

        // Denda masuk = payments where type late_fee or damage_fee
        $dendaMasuk = (clone $paymentQuery)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->whereIn('type', ['late_fee', 'damage_fee'])
            ->sum('amount');

        // Piutang = total_amount - paid_amount for rentals
        $piutang = (clone $rentalQuery)
            ->selectRaw('SUM(total_amount - paid_amount) as p')
            ->value('p') ?? 0;


        // Rentals 30 days (aggregate group by date)
        $rentals30Raw = Rental::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->when(!is_null($salesUserId), fn($q) => $q->where('created_by', $salesUserId))
            ->whereBetween('rental_date', [$start30->toDateString(), $end30->toDateString()])
            ->selectRaw('DATE(rental_date) as d, COUNT(*) as c')
            ->groupByRaw('DATE(rental_date)')
            ->orderByRaw('DATE(rental_date)')
            ->get();

        // Transform in PHP (no extra DB queries)
        $rentals30 = $rentals30Raw->map(fn($row) => [
            'date' => Carbon::parse($row->d, 'UTC')->setTimezone($tz)->format('d M'),
            'count' => (int) $row->c,
        ])->values()->all();

        // Omzet 30 days
        $omzet30 = Payment::query()
            ->whereBetween('paid_at', [$start30->copy()->startOfDay(), $end30->copy()->endOfDay()])
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as s')
            ->groupByRaw('DATE(paid_at)')
            ->orderByRaw('DATE(paid_at)')
            ->get()
            ->map(fn($row) => [
                'date' => Carbon::parse($row->d, 'UTC')->setTimezone($tz)->format('d M'),
                'revenue' => (float) $row->s,
            ])
            ->values();

        // Status rental
        $statusRental = (clone $rentalQuery)
            ->selectRaw('rental_status, COUNT(*) as c')
            ->groupBy('rental_status')
            ->get()
            ->keyBy('rental_status');

        $statusRentalPayload = [
            'waiting' => (int) ($statusRental['waiting']->c ?? 0),
            'active' => (int) ($statusRental['active']->c ?? 0),
            'overdue' => (int) ($statusRental['overdue']->c ?? 0),
            'returned' => (int) ($statusRental['returned']->c ?? 0),
        ];

        // Top products
        $topProducts = RentalItem::query()
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->when(!empty($branchIds), fn($q) => $q->whereIn('rentals.branch_id', $branchIds))
            ->when(!is_null($salesUserId), fn($q) => $q->where('rentals.created_by', $salesUserId))
            ->selectRaw('rental_items.product_name, SUM(rental_items.quantity) as total')
            ->groupBy('rental_items.product_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($p) => ['name' => $p->product_name, 'count' => (int) $p->total])
            ->values();

        // Widgets

        $latestTransactions = Rental::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->when(!is_null($salesUserId), fn($q) => $q->where('created_by', $salesUserId))
            ->with(['customer', 'payments' => fn($q) => $q->orderByDesc('paid_at')->limit(1)])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'invoice' => $r->invoice_number,
                'customer' => $r->customer?->name,
                'status' => $r->rental_status,
                'paid_amount' => (float) ($r->payments->first()->amount ?? 0),
                'payment_status' => $r->payment_status,
            ]);


        $latestInvoices = Rental::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->when(!is_null($salesUserId), fn($q) => $q->where('created_by', $salesUserId))
            ->with(['customer'])
            ->latest('rental_date')
            ->limit(8)
            ->get(['id', 'invoice_number', 'customer_id', 'total_amount', 'payment_status', 'rental_date']);

        $reminderPengembalian = Rental::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->when(!is_null($salesUserId), fn($q) => $q->where('created_by', $salesUserId))
            ->whereIn('rental_status', ['active', 'overdue'])
            ->whereDate('return_due_date', $today)
            ->with('customer')
            ->orderBy('return_due_date')
            ->limit(6)
            ->get(['id', 'invoice_number', 'customer_id', 'return_due_date', 'total_amount']);

        $reminderBelumLunas = Rental::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->when(!is_null($salesUserId), fn($q) => $q->where('created_by', $salesUserId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereDate('rental_date', $today)
            ->with('customer')
            ->limit(6)
            ->get(['id', 'invoice_number', 'customer_id', 'rental_date', 'payment_status', 'total_amount', 'paid_amount']);

        $almostEmptyProducts = Product::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->where('stock_available', '>', 0)
            ->where('stock_available', '<=', 3)
            ->where('status', 'available')
            ->orderBy('stock_available')
            ->limit(6)
            ->get(['id', 'name', 'stock_available', 'rental_price', 'photo']);

        $newCustomers = Customer::query()
            ->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'name', 'phone', 'created_at']);

        // Calculate pengembalian_hari_ini
        $pengembalianHariIni = RentalReturn::query()
            ->whereDate('returned_at', $today)
            ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
            ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
            ->count();

        $performance = null;
        if (!is_null($salesUserId)) {
            $salesUser = User::query()->where('role', 'sales')->find($salesUserId);
            if ($salesUser) {
                $performanceRentals = Rental::query()
                    ->where('created_by', $salesUserId)
                    ->when(!empty($branchIds), fn ($q) => $q->whereIn('branch_id', $branchIds))
                    ->when($dateFrom, fn ($q) => $q->whereDate('rental_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('rental_date', '<=', $dateTo));

                $performancePayments = Payment::query()
                    ->whereHas('rental', function (Builder $q) use ($salesUserId, $branchIds) {
                        $q->where('created_by', $salesUserId)
                            ->when(!empty($branchIds), fn ($rental) => $rental->whereIn('branch_id', $branchIds));
                    })
                    ->when($dateFrom, fn ($q) => $q->whereDate('paid_at', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('paid_at', '<=', $dateTo));

                $performanceReturns = Rental::query()
                    ->where('created_by', $salesUserId)
                    ->where('rental_status', Rental::STATUS_RETURNED)
                    ->when(!empty($branchIds), fn ($q) => $q->whereIn('branch_id', $branchIds))
                    ->when($dateFrom, fn ($q) => $q->whereDate('actual_return_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('actual_return_date', '<=', $dateTo));

                $performanceRows = (clone $performanceRentals)
                    ->with([
                        'branch',
                        'customer',
                        'createdBy',
                        'returnedBy',
                        'payments' => fn ($q) => $q->with('receivedBy')->orderByDesc('paid_at')->limit(1),
                    ])
                    ->latest('rental_date')
                    ->limit(10)
                    ->get()
                    ->map(fn (Rental $rental) => [
                        'date' => $rental->rental_date?->format('d/m/Y'),
                        'invoice' => $rental->invoice_number,
                        'customer' => $rental->customer?->name ?? '-',
                        'sales' => $rental->createdBy?->name ?? '-',
                        'branch' => $rental->branch?->name ?? '-',
                        'amount' => (float) $rental->total_amount,
                        'status' => $rental->rental_status,
                        'status_label' => $rental->rental_status === Rental::STATUS_RETURNED
                            ? $rental->status_label
                            : null,
                        'payment_operator' => $rental->payments->first()?->receivedBy?->name,
                        'return_operator' => $rental->returnedBy?->name,
                    ])
                    ->values();

                $performance = [
                    'sales' => $salesUser->name,
                    'branch' => $branchIds
                        ? Branch::whereKey($branchIds[0])->value('name')
                        : 'Semua cabang',
                    'transactions' => (clone $performanceRentals)->count(),
                    'completed' => (clone $performanceRentals)->completed()->count(),
                    'active' => (clone $performanceRentals)->whereIn('rental_status', [Rental::STATUS_ACTIVE, Rental::STATUS_OVERDUE])->count(),
                    'customers' => (clone $performanceRentals)->distinct('customer_id')->count('customer_id'),
                    'revenue' => (float) (clone $performancePayments)->sum('amount'),
                    'returns' => (clone $performanceReturns)->count(),
                    'points' => (int) $salesUser->total_points,
                    'rows' => $performanceRows,
                ];
            }
        }

        return [
            'meta' => [
                'role' => $filters['scope_type'],
                'scope_type' => $filters['scope_type'],
                'tz' => $tz,
                'generated_at' => $now->toISOString(),
            ],
            'stats' => [
                // Customer
                'total_customers' => (int) $totalCustomers,
                'customer_aktif' => (int) $customerActive,
                'customer_baru_hari_ini' => (int) $newCustomersToday,
                'customer_baru_minggu_ini' => (int) (clone $customerQuery)
                    ->whereBetween('created_at', [$startOfWeek->toDateString(), $now->toDateString()])
                    ->count(),
                'customer_baru_bulan_ini' => (int) $newCustomersThisMonth,

                // Produk
                'total_produk' => (int) $totalProducts,
                'produk_tersedia' => (int) $productsAvailable,
                'produk_disewa' => (int) $productsRented,
                'produk_maintenance' => (int) $productsMaintenance,
                'produk_laundry' => (int) (clone $productQuery)
                    ->where('status', 'laundry')
                    ->count(),
                'produk_siap_disewakan' => (int) (clone $productQuery)
                    ->where('status', 'siap_disewakan')
                    ->count(),
                'produk_hampir_habis' => (int) $productsAlmostEmpty,

                // Rental
                'total_penyewaan' => (int) $totalRentals,
                'penyewaan_hari_ini' => (int) $rentalsToday,
                'penyewaan_minggu_ini' => (int) $rentalsThisWeek,
                'penyewaan_bulan_ini' => (int) $rentalsThisMonth,
                'rental_waiting' => (int) $rentalWaiting,
                'rental_active' => (int) $rentalActive,
                'rental_overdue' => (int) $rentalLate,
                'rental_berjalan' => (int) $rentalActive,
                'rental_terlambat' => (int) $rentalLate,
                'rental_selesai' => (int) $rentalFinished,
                'rental_finished' => (int) $rentalFinished,
                'rental_cancelled' => (int) $rentalCancelled,

                // Keuangan
                'pendapatan_hari_ini' => (float) $revenueToday,
                'omzet_hari_ini' => (float) $revenueToday,
                'omzet_minggu_ini' => (float) $revenueThisWeek,
                'omzet_bulan_ini' => (float) $revenueThisMonth,
                'omzet_tahun_ini' => (float) $revenueThisYear,
                'total_invoice' => (int) (clone $rentalQuery)->count(),
                'invoice_hari_ini' => (int) $invoiceToday,
                'invoice_belum_dibayar' => (int) $invoiceUnpaidCount,
                'receipt_hari_ini' => (int) $receiptToday,
                'pengembalian_hari_ini' => (int) $pengembalianHariIni,
                'deposit_masuk' => (float) $depositMasuk,
                'denda_keterlambatan' => (float) $dendaMasuk,
                'denda_kerusakan' => (float) (clone $paymentQuery)
                    ->when(!empty($branchIds), fn($q) => $q->whereHas('rental', $applyRentalBranchFilter))
                    ->when(!is_null($salesUserId), fn($q) => $q->whereHas('rental', fn($r) => $r->where('created_by', $salesUserId)))
                    ->where('type', 'damage_fee')
                    ->sum('amount'),
                'total_piutang' => (float) $piutang,

                // Sales
                'total_sales' => (int) User::query()->where('role', 'sales')->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))->count(),
                'sales_aktif' => (int) User::query()->where('role', 'sales')->when(!empty($branchIds), fn($q) => $q->whereIn('branch_id', $branchIds))->where('is_active', 1)->count(),
                'target_sales' => (int) (clone $rentalQuery)->count(),
                'progress_target' => (int) (clone $rentalQuery)->count(),
            ],
            'performance' => $performance,
            'charts' => [
                'rentals_30_days' => $rentals30,
                'omzet_30_days' => $omzet30,
                'status_rental' => $statusRentalPayload,
                'top_products' => $topProducts,
                'chart_label' => $chartLabel,
            ],
            'widgets' => [
                'transaksi_terbaru' => $latestTransactions,
                'invoice_terbaru' => $latestInvoices,
                'reminder_pengembalian' => $reminderPengembalian,
                'reminder_belum_lunas' => $reminderBelumLunas,
                'produk_hampir_habis' => $almostEmptyProducts,
                'customer_terbaru' => $newCustomers,
                'top_produk' => $topProducts,
                'top_produk_terbaru' => $topProducts,
            ],
        ];
    }
}
