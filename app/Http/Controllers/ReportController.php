<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Product;
use App\Models\Payment;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function resolvedBranchId(Request $request): ?int
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            if ($request->filled('branch_id')) {
                $validated = $request->validate(['branch_id' => ['integer', 'exists:branches,id']]);
                return (int) $validated['branch_id'];
            }

            return null;
        }

        if ($user->isAdminToko()) {
            $allowedBranchIds = $this->adminBranchIds($user);
            abort_if($allowedBranchIds === [], 403, 'Akun Admin Toko belum memiliki cabang yang ditetapkan.');

            if ($request->filled('branch_id')) {
                $validated = $request->validate(['branch_id' => ['integer', 'exists:branches,id']]);
                $requestedId = (int) $validated['branch_id'];
                abort_unless(in_array($requestedId, $allowedBranchIds, true), 403, 'Cabang di luar cakupan akses.');
                return $requestedId;
            }

            $primaryBranchId = (int) ($user->branch_id ?? 0);
            return in_array($primaryBranchId, $allowedBranchIds, true) ? $primaryBranchId : $allowedBranchIds[0];
        }

        return (int) $user->branch_id;
    }

    private function adminBranchIds(User $user): array
    {
        return $user->branch_id ? [(int) $user->branch_id] : [];
    }

    private function resolvedBranchIds(): ?array
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return null;
        }

        if ($user->isAdminToko()) {
            return $this->adminBranchIds($user);
        }

        return $user->accessible_branch_ids;
    }

    private function baseRentalQuery(Request $request, ?int $branchId)
    {
        $query = Rental::with(['branch', 'customer', 'createdBy', 'items', 'payments']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('rental_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('rental_date', '<=', $request->input('end_date'));
        }

        return $query;
    }

    private function sharedViewData(Request $request): array
    {
        $user         = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();

        $branches = $isSuperAdmin
            ? Branch::orderBy('name')->get()
            : ($user->isAdminToko()
                ? Branch::whereIn('id', $this->adminBranchIds($user))->orderBy('name')->get()
                : collect());

        $selectedBranchId = $this->resolvedBranchId($request);

        return compact('isSuperAdmin', 'branches', 'selectedBranchId');
    }

    private function resolveActivityFilter(Request $request): array
    {
        $validTabs = ['all', 'rental', 'payment', 'customer', 'product', 'user'];
        $tab = in_array($request->input('tab', 'all'), $validTabs, true)
            ? $request->input('tab', 'all')
            : 'all';
        $action = $request->input('action');

        $actionMap = [
            'create_rental' => ['tab' => 'rental', 'label' => 'Buat Sewa'],
            'rental_deleted' => ['tab' => 'rental', 'label' => 'Batalkan Sewa'],
            'handover_rental' => ['tab' => 'rental', 'label' => 'Selesaikan Sewa'],
            'return_rental' => ['tab' => 'rental', 'label' => 'Selesaikan Sewa'],
            'recalculate_payment_status' => ['tab' => 'rental', 'label' => 'Ubah Sewa'],
            'update_transaction' => ['tab' => 'rental', 'label' => 'Ubah Sewa'],
            'delete_transaction' => ['tab' => 'rental', 'label' => 'Batalkan Sewa'],
            'process_payment' => ['tab' => 'payment', 'label' => 'Terima Pembayaran'],
            'record_refund' => ['tab' => 'payment', 'label' => 'Batalkan Pembayaran'],
            'create_payment' => ['tab' => 'payment', 'label' => 'Terima Pembayaran'],
            'update_payment' => ['tab' => 'payment', 'label' => 'Ubah Pembayaran'],
            'delete_payment' => ['tab' => 'payment', 'label' => 'Batalkan Pembayaran'],
            'void_payment' => ['tab' => 'payment', 'label' => 'Batalkan Pembayaran'],
            'refund_payment' => ['tab' => 'payment', 'label' => 'Batalkan Pembayaran'],
            'create_customer' => ['tab' => 'customer', 'label' => 'Tambah Pelanggan'],
            'update_customer' => ['tab' => 'customer', 'label' => 'Ubah Data Pelanggan'],
            'delete_customer' => ['tab' => 'customer', 'label' => 'Hapus Pelanggan'],
            'soft_delete_customer' => ['tab' => 'customer', 'label' => 'Hapus Pelanggan'],
            'force_delete_customer' => ['tab' => 'customer', 'label' => 'Hapus Pelanggan'],
            'restore_customer' => ['tab' => 'customer', 'label' => 'Pulihkan Pelanggan'],
            'create_product' => ['tab' => 'product', 'label' => 'Tambah Produk'],
            'update_product' => ['tab' => 'product', 'label' => 'Ubah Produk'],
            'update_product_stock' => ['tab' => 'product', 'label' => 'Ubah Stok'],
            'delete_product' => ['tab' => 'product', 'label' => 'Hapus Produk'],
            'create_user' => ['tab' => 'user', 'label' => 'Tambah Pengguna'],
            'update_user' => ['tab' => 'user', 'label' => 'Ubah Pengguna'],
            'delete_user' => ['tab' => 'user', 'label' => 'Hapus Pengguna'],
            'activate_user' => ['tab' => 'user', 'label' => 'Aktifkan Pengguna'],
            'deactivate_user' => ['tab' => 'user', 'label' => 'Nonaktifkan Pengguna'],
        ];

        $labelToActions = [];
        foreach ($actionMap as $actionCode => $mapping) {
            $labelToActions[$mapping['label']][] = $actionCode;
        }

        $resolvedAction = null;
        if ($action) {
            $resolvedAction = $labelToActions[$action]
                ?? (isset($actionMap[$action]) ? [$action] : [$action]);

            if ($tab !== 'all') {
                $resolvedAction = array_values(array_filter(
                    $resolvedAction,
                    fn ($code) => isset($actionMap[$code]) && $actionMap[$code]['tab'] === $tab
                ));

                if ($resolvedAction === []) {
                    $action = null;
                    $resolvedAction = null;
                }
            }
        }

        return [
            'actionMap' => $actionMap,
            'labelToActions' => $labelToActions,
            'resolvedAction' => $resolvedAction,
            'action' => $action,
            'tab' => $tab,
        ];
    }

    private function applyActivityLogFilters(Builder $query, ?int $branchId, array $filter): Builder
    {
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($filter['resolvedAction']) {
            $query->whereIn('action', $filter['resolvedAction']);
        }

        if ($filter['tab'] !== 'all') {
            $query->where(function ($q) use ($filter) {
                match ($filter['tab']) {
                    'rental' => $q->whereIn('action', [
                        'create_rental', 'rental_deleted', 'handover_rental',
                        'return_rental', 'recalculate_payment_status',
                        'update_transaction', 'delete_transaction',
                    ]),
                    'payment' => $q->whereIn('action', [
                        'process_payment', 'record_refund',
                        'create_payment', 'update_payment', 'delete_payment',
                        'void_payment', 'refund_payment',
                    ]),
                    'customer' => $q->where('model_type', Customer::class),
                    'product' => $q->where('model_type', Product::class),
                    'user' => $q->where('model_type', User::class),
                    default => $q,
                };
            });
        }

        return $query;
    }

    private function activityLogBranchIds(Request $request): ?array
    {
        $user = $request->user();
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $requestedId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($user->isSuperAdmin()) {
            return $requestedId === null ? null : [$requestedId];
        }

        if ($user->isAdminToko()) {
            $branchIds = $this->adminBranchIds($user);
            abort_if($branchIds === [], 403, 'Akun Admin Toko belum memiliki cabang yang ditetapkan.');
            abort_if($requestedId !== null && ! in_array($requestedId, $branchIds, true), 403, 'Cabang di luar cakupan akses.');

            return $requestedId === null ? $branchIds : [$requestedId];
        }

        abort(403);
    }

    private function applyActivityLogBranchScope(Builder $query, ?array $branchIds): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($branchIds) {
            $scope->where(function (Builder $nonRentalLogs) use ($branchIds) {
                $nonRentalLogs->whereIn('branch_id', $branchIds)
                    ->where(function (Builder $modelType) {
                        $modelType->whereNull('model_type')
                            ->orWhere('model_type', '!=', Rental::class);
                    });
            })
                ->orWhere(function (Builder $rentalLogs) use ($branchIds) {
                    $rentalLogs->where('model_type', Rental::class)
                            ->whereIn('model_id', Rental::query()
                            ->whereIn('branch_id', $branchIds)
                            ->select('id'));
                });
        });
    }

    private function applyPeriodFilter(Builder $query, Request $request, string $dateColumn = 'created_at'): Builder
    {
        $period = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($period === 'week') {
            $query->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate($dateColumn, today());
        } else {
            if ($dateFrom) {
                $query->whereDate($dateColumn, '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate($dateColumn, '<=', $dateTo);
            }
        }

        return $query;
    }

    private function buildActionOptions(Collection $allActions, string $tab, array $actionMap): Collection
    {
        $filteredActions = $allActions->filter(function ($action) use ($tab, $actionMap) {
            if ($tab === 'all') {
                return true;
            }
            $mapping = $actionMap[$action] ?? null;
            return $mapping && $mapping['tab'] === $tab;
        })->values();

        $seenLabels = [];
        $actionOptions = [];
        foreach ($filteredActions as $action) {
            $mapping = $actionMap[$action] ?? null;
            $label = $mapping ? $mapping['label'] : $action;
            if (isset($seenLabels[$label])) {
                continue;
            }
            $seenLabels[$label] = true;
            $actionOptions[] = [
                'value' => $label,
                'label' => $label,
            ];
        }

        return collect($actionOptions);
    }

    // =========================================================================
    // LAPORAN KEUANGAN
    // =========================================================================

    public function index(Request $request)
    {
        return $this->payments($request);
    }

    public function revenue(Request $request)
    {
        $branchId = $this->resolvedBranchId($request);
        $shared   = $this->sharedViewData($request);

        $period = $request->input('period');

        $query = Rental::selectRaw('
                DATE(rental_date)  AS date,
                SUM(total_amount)  AS total_revenue,
                SUM(late_fee)      AS total_late_fee,
                COUNT(*)           AS total_rentals
            ')
            ->where('payment_status', Rental::PAYMENT_PAID);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($period === 'week') {
            $query->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('rental_date', today());
        } else {
            if ($request->filled('start_date')) {
                $query->whereDate('rental_date', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('rental_date', '<=', $request->input('end_date'));
            }
        }

        $revenueData  = $query->groupByRaw('DATE(rental_date)')->orderBy('date', 'desc')->get();
        $totalRevenue = $revenueData->sum('total_revenue');
        $totalLateFee = $revenueData->sum('total_late_fee');
        $totalRentals = $revenueData->sum('total_rentals');

        if ($request->ajax()) {
            return view('reports.partials.revenue-content', array_merge($shared, [
                'revenueData'  => $revenueData,
                'totalRevenue' => $totalRevenue,
                'totalLateFee' => $totalLateFee,
                'totalRentals' => $totalRentals,
            ]))->render();
        }

        return view('reports.revenue', array_merge($shared, [
            'revenueData'  => $revenueData,
            'totalRevenue' => $totalRevenue,
            'totalLateFee' => $totalLateFee,
            'totalRentals' => $totalRentals,
        ]));
    }

    // =========================================================================
    // LAPORAN TRANSAKSI
    // =========================================================================

  public function transactions(Request $request)
{
    $branchId = $this->resolvedBranchId($request);
    $shared   = $this->sharedViewData($request);

    $search   = $request->input('search');
    $status   = $request->input('status');
    $period   = $request->input('period');
    $dateFrom = $request->input('date_from');
    $dateTo   = $request->input('date_to');

    $query = Rental::with(['customer', 'branch', 'createdBy', 'items', 'payments']);

    if ($branchId !== null) {
        $query->where('branch_id', $branchId);
    }

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'like', "%{$search}%")
              ->orWhereHas('customer', fn($c) => $c
                  ->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
              );
        });
    }

    if ($status) {
        $query->where('rental_status', $status);
    }

    if ($period === 'week') {
        $query->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
    } elseif ($period === 'month') {
        $query->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
    } elseif ($period === 'today') {
        $query->whereDate('rental_date', today());
    } else {
        if ($dateFrom) {
            $query->whereDate('rental_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('rental_date', '<=', $dateTo);
        }
    }

    $rentals = $query->latest('rental_date')->paginate(25)->withQueryString();

    // Summary — base query sama tapi tanpa paginate
    $baseQuery = Rental::query();
    if ($branchId !== null) {
        $baseQuery->where('branch_id', $branchId);
    }

    if ($period === 'week') {
        $baseQuery->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
    } elseif ($period === 'month') {
        $baseQuery->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
    } elseif ($period === 'today') {
        $baseQuery->whereDate('rental_date', today());
    } else {
        if ($dateFrom) {
            $baseQuery->whereDate('rental_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseQuery->whereDate('rental_date', '<=', $dateTo);
        }
    }

    $summary = [
        'total'      => (clone $baseQuery)->count(),
        'pending'    => (clone $baseQuery)->where('rental_status', 'waiting')->count(),
        'active'     => (clone $baseQuery)->whereIn('rental_status', ['active', 'overdue'])->count(),
        'completed'  => (clone $baseQuery)->completed()->count(),
        'cancelled'  => (clone $baseQuery)->where('rental_status', 'cancelled')->count(),
        'total_nilai' => (clone $baseQuery)->completed()->sum('total_amount'),
    ];

    $statuses = Rental::distinct()->pluck('rental_status')->sort()->values();

    if ($request->ajax()) {
        return view('reports.partials.transactions-content', array_merge($shared, [
            'rentals'  => $rentals,
            'summary'  => $summary,
            'statuses' => $statuses,
            'period'   => $period,
            'search'   => $search,
            'status'   => $status,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
        ]))->render();
    }

    return view('reports.transactions', array_merge($shared, [
        'rentals'  => $rentals,
        'summary'  => $summary,
        'statuses' => $statuses,
        'period'   => $period,
        'search'   => $search,
        'status'   => $status,
        'dateFrom' => $dateFrom,
        'dateTo'   => $dateTo,
    ]));
}

    // =========================================================================
    // LAPORAN STOK PRODUK
    // =========================================================================

 public function stock(Request $request)
{
    $branchId = $this->resolvedBranchId($request);
    $shared   = $this->sharedViewData($request);

    $search   = $request->input('search');
    $category = $request->input('category');

    $query = Product::with(['branch', 'category']);

    if ($branchId !== null) {
        $query->where('branch_id', $branchId);
    }

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%");
        });
    }

    if ($category) {
        $query->where('category_id', $category);
    }

    // Base query tanpa filter untuk summary
    $baseQuery = Product::query();
    if ($branchId !== null) {
        $baseQuery->where('branch_id', $branchId);
    }

    $totalProducts = (clone $baseQuery)->count();
    $totalAvail    = (clone $baseQuery)->where('status', 'available')->count();
    $totalRented   = (clone $baseQuery)->where('status', 'rented')->count();
    $totalStock    = $totalAvail + $totalRented;
    $activeRentals = $totalRented;
    $outOfStock    = (clone $baseQuery)->where('stock_available', 0)->count();
    $lowStock      = (clone $baseQuery)->where('stock_available', '>', 0)
                                       ->where('stock_available', '<=', 2)->count();

    $products   = $query->orderBy('name')->paginate(25)->withQueryString();
    $categories = \App\Models\Category::orderBy('name')->get();

    if ($request->ajax()) {
        return view('reports.partials.stock-content', array_merge($shared, [
            'products'      => $products,
            'totalProducts' => $totalProducts,
            'totalStock'    => $totalStock,
            'totalAvail'    => $totalAvail,
            'totalRented'   => $totalRented,
            'activeRentals' => $activeRentals,
            'outOfStock'    => $outOfStock,
            'lowStock'      => $lowStock,
            'categories'    => $categories,
            'search'        => $search,
            'category'      => $category,
        ]))->render();
    }

    return view('reports.stock', array_merge($shared, [
        'products'      => $products,
        'totalProducts' => $totalProducts,
        'totalStock'    => $totalStock,
        'totalAvail'    => $totalAvail,
        'totalRented'   => $totalRented,
        'activeRentals' => $activeRentals,
        'outOfStock'    => $outOfStock,
        'lowStock'      => $lowStock,
        'categories'    => $categories,
        'search'        => $search,
        'category'      => $category,
    ]));
}

    // =========================================================================
    // LAPORAN PENGEMBALIAN
    // =========================================================================

   public function returns(Request $request)
{
    $branchId = $this->resolvedBranchId($request);
    $shared   = $this->sharedViewData($request);

    $period   = $request->input('period');
    $dateFrom = $request->input('date_from');
    $dateTo   = $request->input('date_to');

    // Base query per branch
    $base = Rental::with(['customer', 'branch', 'createdBy', 'items']);
    if ($branchId !== null) {
        $base->where('branch_id', $branchId);
    }

    // Summary
    $summary = [
        'returned'  => (clone $base)->whereIn('rental_status', ['returned', 'completed'])->count(),
        'due_today' => (clone $base)->whereIn('rental_status', ['active', 'overdue'])
                                    ->whereDate('return_due_date', today())->count(),
        'overdue'   => (clone $base)->where('rental_status', 'overdue')->count(),
    ];

    // Terlambat — overdue
    $overdue = (clone $base)->where('rental_status', 'overdue')
                            ->orderBy('return_due_date')
                            ->get();

    // Jatuh tempo hari ini
    $dueToday = (clone $base)->whereIn('rental_status', ['active', 'overdue'])
                             ->whereDate('return_due_date', today())
                             ->orderBy('return_due_date')
                             ->get();

    // Riwayat pengembalian (dengan filter tanggal)
    $returnedQuery = (clone $base)->whereIn('rental_status', ['returned', 'completed']);

    if ($period === 'week') {
        $returnedQuery->whereBetween('actual_return_date', [now()->startOfWeek()->toDateString(), now()->endOfDay()->toDateString()]);
    } elseif ($period === 'month') {
        $returnedQuery->whereBetween('actual_return_date', [now()->startOfMonth()->toDateString(), now()->endOfDay()->toDateString()]);
    } elseif ($period === 'today') {
        $returnedQuery->whereDate('actual_return_date', today());
    } else {
        if ($dateFrom) {
            $returnedQuery->whereDate('actual_return_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $returnedQuery->whereDate('actual_return_date', '<=', $dateTo);
        }
    }

    $returned = $returnedQuery->latest('actual_return_date')->paginate(25)->withQueryString();

    if ($request->ajax()) {
        return view('reports.partials.returns-content', array_merge($shared, [
            'summary'  => $summary,
            'overdue'  => $overdue,
            'dueToday' => $dueToday,
            'returned' => $returned,
            'period'   => $period,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
        ]))->render();
    }

    return view('reports.returns', array_merge($shared, [
        'summary'  => $summary,
        'overdue'  => $overdue,
        'dueToday' => $dueToday,
        'returned' => $returned,
        'period'   => $period,
        'dateFrom' => $dateFrom,
        'dateTo'   => $dateTo,
    ]));
}
    // =========================================================================
    // EXPORT CSV (tanpa package, langsung bisa dibuka di Excel)
    // =========================================================================

    public function exportExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $rentals = $this->baseRentalQuery($request, $branchId)
            ->latest('rental_date')
            ->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-rental-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($rentals) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel baca karakter Indonesia dengan benar
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header kolom — separator semicolon untuk Excel versi Indonesia
            fputcsv($handle, [
                'No. Invoice',
                'Tanggal Rental',
                'Jatuh Tempo',
                'Tanggal Kembali',
                'Cabang',
                'Pelanggan',
                'Status Rental',
                'Status Bayar',
                'Subtotal',
                'Diskon',
                'Denda',
                'Total',
                'Sudah Dibayar',
                'Sisa Tagihan',
                'Dibuat Oleh',
            ], ';');

            foreach ($rentals as $rental) {
                /** @var \Illuminate\Support\Carbon $rentalDate */
                $rentalDate = $rental->rental_date;
                /** @var \Illuminate\Support\Carbon $returnDueDate */
                $returnDueDate = $rental->return_due_date;
                /** @var \Illuminate\Support\Carbon|null $actualReturnDate */
                $actualReturnDate = $rental->actual_return_date;

                fputcsv($handle, [
                    $rental->invoice_number,
                    $rentalDate?->format('d/m/Y'),
                    $returnDueDate?->format('d/m/Y'),
                    $actualReturnDate?->format('d/m/Y') ?? '-',
                    $rental->branch?->name,
                    $rental->customer?->name,
                    $rental->status_label,
                    $rental->payment_status_label,
                    $rental->subtotal,
                    $rental->discount,
                    $rental->late_fee,
                    $rental->total_amount,
                    $rental->paid_amount,
                    $rental->remaining_amount,
                    $rental->createdBy?->name,
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // =========================================================================
    // EXPORT PDF
    // =========================================================================

    public function exportPdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $rentals      = $this->baseRentalQuery($request, $branchId)->latest('rental_date')->get();
        $totalRevenue = $rentals->where('payment_status', Rental::PAYMENT_PAID)->sum('total_amount');
        $totalLateFee = $rentals->sum('late_fee');

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $pdf = Pdf::loadView('reports.pdf.transactions', [
            'rentals'      => $rentals,
            'totalRevenue' => $totalRevenue,
            'totalLateFee' => $totalLateFee,
            'branchName'   => $branchName,
            'startDate'    => $request->input('start_date'),
            'endDate'      => $request->input('end_date'),
            'generatedAt'  => now()->format('d/m/Y H:i'),
            'generatedBy'  => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // LAPORAN LOG AKTIVITAS
    // =========================================================================

    public function activityLog(Request $request)
    {
        $user = Auth::user();
        $branchIds = $this->activityLogBranchIds($request);
        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $branchIds ?? [])->orderBy('name')->get();
        $selectedBranchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : (count($branchIds ?? []) === 1 ? $branchIds[0] : null);
        $shared = compact('branches', 'selectedBranchId') + ['isSuperAdmin' => $user->isSuperAdmin()];

        $filter = $this->resolveActivityFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period');
        $search = $request->input('search');

        $query = ActivityLog::with(['user', 'branch', 'rental.branch']);
        $query = $this->applyActivityLogBranchScope($query, $branchIds);
        $query = $this->applyActivityLogFilters($query, null, $filter);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $query = $this->applyPeriodFilter($query, $request);

        $logs = $query->latest('created_at')->paginate(50)->withQueryString();

        $actionQuery = ActivityLog::query();
        $this->applyActivityLogBranchScope($actionQuery, $branchIds);
        $allActions = $actionQuery->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $actionOptions = $this->buildActionOptions($allActions, $filter['tab'], $filter['actionMap']);

        $tabs = [
            'all' => 'Semua Aktivitas',
            'rental' => 'Penyewaan',
            'payment' => 'Pembayaran',
            'customer' => 'Pelanggan',
            'product' => 'Produk',
            'user' => 'Pengguna',
        ];

        $baseQuery = ActivityLog::query();
        $baseQuery = $this->applyActivityLogBranchScope($baseQuery, $branchIds);
        $baseQuery = $this->applyActivityLogFilters($baseQuery, null, $filter);
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }
        $baseQuery = $this->applyPeriodFilter($baseQuery, $request);

        $summaryTotal = (clone $baseQuery)->count();
        $summaryToday = (clone $baseQuery)->whereDate('created_at', today())->count();
        $summaryActiveUsers = (clone $baseQuery)->distinct('user_id')->count('user_id');

        if ($request->ajax()) {
            return view('reports.partials.activity-log-content', array_merge($shared, [
                'logs' => $logs,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'period' => $period,
                'search' => $search,
                'action' => $filter['action'],
                'tab' => $filter['tab'],
                'actionOptions' => $actionOptions,
                'tabs' => $tabs,
                'summaryTotal' => $summaryTotal,
                'summaryToday' => $summaryToday,
                'summaryActiveUsers' => $summaryActiveUsers,
            ]))->render();
        }

        return view('reports.activity-log', array_merge($shared, [
            'logs' => $logs,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'period' => $period,
            'search' => $search,
            'action' => $filter['action'],
            'tab' => $filter['tab'],
            'actionOptions' => $actionOptions,
            'tabs' => $tabs,
            'summaryTotal' => $summaryTotal,
            'summaryToday' => $summaryToday,
            'summaryActiveUsers' => $summaryActiveUsers,
        ]));
    }

    public function exportActivityLog(Request $request)
    {
        $branchIds = $this->activityLogBranchIds($request);

        $search = $request->input('search');

        $filter = $this->resolveActivityFilter($request);

        $query = ActivityLog::with(['user', 'branch', 'rental.branch']);
        $query = $this->applyActivityLogBranchScope($query, $branchIds);
        $query = $this->applyActivityLogFilters($query, null, $filter);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $query = $this->applyPeriodFilter($query, $request);

        $logs = $query->orderByDesc('created_at')->get();

        $branchId = count($branchIds ?? []) === 1 ? $branchIds[0] : null;
        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'log-aktivitas-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Waktu',
                'User',
                'Cabang',
                'Aksi',
                'Tipe Model',
                'ID Model',
                'Deskripsi',
                'IP Address',
            ], ';');

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->format('d/m/Y H:i:s'),
                    $log->user?->name,
                    ($log->model_type === Rental::class ? $log->rental?->branch?->name : null) ?? $log->branch?->name,
                    $log->action,
                    $log->model_type,
                    $log->model_id,
                    $log->description,
                    $log->ip_address,
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    // =========================================================================
    // EXPORT LOG AKTIVITAS (PDF)
    // =========================================================================

    public function exportActivityLogPdf(Request $request)
    {
        $user = Auth::user();
        $branchIds = $this->activityLogBranchIds($request);

        $search = $request->input('search');

        $filter = $this->resolveActivityFilter($request);

        $query = ActivityLog::with(['user', 'branch', 'rental.branch']);
        $query = $this->applyActivityLogBranchScope($query, $branchIds);
        $query = $this->applyActivityLogFilters($query, null, $filter);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $query = $this->applyPeriodFilter($query, $request);

        $logs = $query->orderByDesc('created_at')->get();

        $branchId = count($branchIds ?? []) === 1 ? $branchIds[0] : null;
        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $periodLabel = match ($request->input('period')) {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            default => ($request->filled('date_from') && $request->filled('date_to') ? "{$request->input('date_from')} s/d {$request->input('date_to')}" : 'Semua Periode'),
        };

        $pdf = Pdf::loadView('reports.pdf.rental-activity-log', [
            'logs'         => $logs,
            'branchName'   => $branchLabel,
            'startDate'    => $request->input('date_from'),
            'endDate'      => $request->input('date_to'),
            'periodLabel'  => $periodLabel,
            'generatedAt'  => now()->format('d/m/Y H:i'),
            'generatedBy'  => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'log-aktivitas-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // EXPORT PENDAPATAN (Excel)
    // =========================================================================

    public function exportRevenueExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $period = $request->input('period');

        $query = Rental::selectRaw('
                DATE(rental_date)  AS date,
                SUM(total_amount)  AS total_revenue,
                SUM(late_fee)      AS total_late_fee,
                COUNT(*)           AS total_rentals
            ')
            ->where('payment_status', Rental::PAYMENT_PAID);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($period === 'week') {
            $query->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('rental_date', today());
        } else {
            if ($request->filled('start_date')) {
                $query->whereDate('rental_date', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('rental_date', '<=', $request->input('end_date'));
            }
        }

        $revenueData = $query->groupByRaw('DATE(rental_date)')->orderBy('date', 'desc')->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-pendapatan-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($revenueData) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Tanggal', 'Total Rental', 'Total Denda', 'Pendapatan'], ';');

            foreach ($revenueData as $row) {
                fputcsv($handle, [
                    \Carbon\Carbon::parse($row->date)->isoFormat('dddd, D MMMM Y'),
                    $row->total_rentals,
                    $row->total_late_fee,
                    $row->total_revenue,
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    // =========================================================================
    // EXPORT PENDAPATAN (PDF)
    // =========================================================================

    public function exportRevenuePdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $period = $request->input('period');

        $query = Rental::selectRaw('
                DATE(rental_date)  AS date,
                SUM(total_amount)  AS total_revenue,
                SUM(late_fee)      AS total_late_fee,
                COUNT(*)           AS total_rentals
            ')
            ->where('payment_status', Rental::PAYMENT_PAID);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($period === 'week') {
            $query->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('rental_date', today());
        } else {
            if ($request->filled('start_date')) {
                $query->whereDate('rental_date', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('rental_date', '<=', $request->input('end_date'));
            }
        }

        $revenueData = $query->groupByRaw('DATE(rental_date)')->orderBy('date', 'desc')->get();
        $totalRevenue = $revenueData->sum('total_revenue');
        $totalLateFee = $revenueData->sum('total_late_fee');
        $totalRentals = $revenueData->sum('total_rentals');

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $pdf = Pdf::loadView('reports.pdf.revenue', [
            'revenueData'  => $revenueData,
            'totalRevenue' => $totalRevenue,
            'totalLateFee' => $totalLateFee,
            'totalRentals' => $totalRentals,
            'branchName'   => $branchName,
            'startDate'    => $request->input('start_date'),
            'endDate'      => $request->input('end_date'),
            'generatedAt'  => now()->format('d/m/Y H:i'),
            'generatedBy'  => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-pendapatan-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // EXPORT TRANSAKSI (Excel)
    // =========================================================================

    public function exportTransactionsExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $search   = $request->input('search');
        $status   = $request->input('status');
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = Rental::with(['customer', 'branch', 'createdBy', 'items', 'payments']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($c) => $c
                      ->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                  );
            });
        }

        if ($status) {
            $query->where('rental_status', $status);
        }

        if ($period === 'week') {
            $query->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('rental_date', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('rental_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('rental_date', '<=', $dateTo);
            }
        }

        $rentals = $query->latest('rental_date')->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-transaksi-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($rentals) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'No. Invoice',
                'Tanggal Transaksi',
                'Tgl Kembali',
                'Cabang',
                'Pelanggan',
                'No. HP',
                'Status Rental',
                'Total',
                'Dibuat Oleh',
            ], ';');

            foreach ($rentals as $rental) {
                /** @var \Illuminate\Support\Carbon $returnDueDate */
                $returnDueDate = $rental->return_due_date;

                fputcsv($handle, [
                    $rental->invoice_number,
                    $rental->created_at?->format('d/m/Y'),
                    $returnDueDate?->format('d/m/Y') ?? '-',
                    $rental->branch?->name,
                    $rental->customer?->name,
                    $rental->customer?->phone,
                    $rental->status_label,
                    $rental->total_amount,
                    $rental->createdBy?->name,
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    // =========================================================================
    // EXPORT TRANSAKSI (PDF)
    // =========================================================================

    public function exportTransactionsPdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $search   = $request->input('search');
        $status   = $request->input('status');
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = Rental::with(['customer', 'branch', 'createdBy', 'items', 'payments']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($c) => $c
                      ->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                  );
            });
        }

        if ($status) {
            $query->where('rental_status', $status);
        }

        if ($period === 'week') {
            $query->whereBetween('rental_date', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('rental_date', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('rental_date', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('rental_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('rental_date', '<=', $dateTo);
            }
        }

        $rentals      = $query->latest('rental_date')->get();
        $totalRevenue = $rentals->where('payment_status', Rental::PAYMENT_PAID)->sum('total_amount');
        $totalLateFee = $rentals->sum('late_fee');

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $pdf = Pdf::loadView('reports.pdf.transactions', [
            'rentals'      => $rentals,
            'totalRevenue' => $totalRevenue,
            'totalLateFee' => $totalLateFee,
            'branchName'   => $branchName,
            'startDate'    => $dateFrom,
            'endDate'      => $dateTo,
            'generatedAt'  => now()->format('d/m/Y H:i'),
            'generatedBy'  => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-transaksi-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // EXPORT PENGEMBALIAN (Excel)
    // =========================================================================

    public function exportReturnsExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = Rental::with(['customer', 'branch', 'createdBy', 'items'])
            ->whereIn('rental_status', ['returned', 'completed']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($period === 'week') {
            $query->whereBetween('actual_return_date', [now()->startOfWeek()->toDateString(), now()->endOfDay()->toDateString()]);
        } elseif ($period === 'month') {
            $query->whereBetween('actual_return_date', [now()->startOfMonth()->toDateString(), now()->endOfDay()->toDateString()]);
        } elseif ($period === 'today') {
            $query->whereDate('actual_return_date', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('actual_return_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('actual_return_date', '<=', $dateTo);
            }
        }

        $returned = $query->latest('actual_return_date')->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-pengembalian-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($returned) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'No. Invoice',
                'Pelanggan',
                'No. HP',
                'Tgl Sewa',
                'Tgl Dikembalikan',
                'Total',
                'Status',
            ], ';');

            foreach ($returned as $t) {
                fputcsv($handle, [
                    $t->invoice_number,
                    $t->customer?->name,
                    $t->customer?->phone,
                    $t->created_at?->format('d/m/Y'),
                    $t->actual_return_date ? \Carbon\Carbon::parse($t->actual_return_date)->format('d/m/Y') : '-',
                    $t->total_amount,
                    $t->rental_status,
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    // =========================================================================
    // EXPORT PENGEMBALIAN (PDF)
    // =========================================================================

    public function exportReturnsPdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = Rental::with(['customer', 'branch', 'createdBy', 'items'])
            ->whereIn('rental_status', ['returned', 'completed']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($period === 'week') {
            $query->whereBetween('actual_return_date', [now()->startOfWeek()->toDateString(), now()->endOfDay()->toDateString()]);
        } elseif ($period === 'month') {
            $query->whereBetween('actual_return_date', [now()->startOfMonth()->toDateString(), now()->endOfDay()->toDateString()]);
        } elseif ($period === 'today') {
            $query->whereDate('actual_return_date', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('actual_return_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('actual_return_date', '<=', $dateTo);
            }
        }

        $returned = $query->latest('actual_return_date')->get();

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $pdf = Pdf::loadView('reports.pdf.returns', [
            'returned'   => $returned,
            'branchName' => $branchName,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'generatedBy' => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-pengembalian-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // EXPORT STOK PRODUK (Excel)
    // =========================================================================

    public function exportStockExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);

        $query = Product::with(['branch', 'category']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $products = $query->orderBy('name')->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-stok-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                '#',
                'Nama Produk',
                'Kode',
                'Kategori',
                'Stok Total',
                'Tersedia',
                'Kondisi',
            ], ';');

            foreach ($products as $i => $product) {
                $condition = match(true) {
                    $product->stock_available == 0 => 'Habis',
                    $product->stock_available <= 2 => 'Hampir Habis',
                    default => 'Tersedia',
                };

                fputcsv($handle, [
                    $i + 1,
                    $product->name,
                    $product->code ?? '-',
                    $product->category?->name ?? '-',
                    $product->stock_total,
                    $product->stock_available,
                    $condition,
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    // =========================================================================
    // EXPORT STOK PRODUK (PDF)
    // =========================================================================

    public function exportStockPdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);

        $query = Product::with(['branch', 'category']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $products = $query->orderBy('name')->get();
        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $pdf = Pdf::loadView('reports.pdf.stock', [
            'products'    => $products,
            'branchName'  => $branchName,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'generatedBy' => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-stok-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // LAPORAN CUSTOMER
    // =========================================================================

    public function customers(Request $request)
    {
        $branchId = $this->resolvedBranchId($request);
        $shared   = $this->sharedViewData($request);

        $search = $request->input('search');

        $query = Customer::with(['branch', 'user'])
            ->select('customers.*')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('COUNT(*)')
                   ->whereColumn('rentals.customer_id', 'customers.id');
            }, 'total_rentals')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('SUM(total_amount)')
                   ->whereColumn('rentals.customer_id', 'customers.id')
                   ->whereIn('rentals.rental_status', ['returned', 'completed']);
            }, 'total_transaction_value')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('MAX(rental_date)')
                   ->whereColumn('rentals.customer_id', 'customers.id');
            }, 'last_rental_date');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('last_rental_date')->paginate(25)->withQueryString();

        if ($request->ajax()) {
            return view('reports.partials.customers-content', array_merge($shared, [
                'customers' => $customers,
                'search'    => $search,
            ]))->render();
        }

        return view('reports.customers', array_merge($shared, [
            'customers' => $customers,
            'search'    => $search,
        ]));
    }

    public function exportCustomersExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $search   = $request->input('search');

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $query = Customer::with(['branch', 'user'])
            ->select('customers.*')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('COUNT(*)')
                   ->whereColumn('rentals.customer_id', 'customers.id');
            }, 'total_rentals')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('SUM(total_amount)')
                   ->whereColumn('rentals.customer_id', 'customers.id')
                   ->whereIn('rentals.rental_status', ['returned', 'completed']);
            }, 'total_transaction_value')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('MAX(rental_date)')
                   ->whereColumn('rentals.customer_id', 'customers.id');
            }, 'last_rental_date');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('last_rental_date')->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-customer-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($customers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Nama Customer',
                'No. HP',
                'Email',
                'Alamat',
                'Total Transaksi',
                'Total Nilai (Selesai)',
                'Transaksi Terakhir',
                'Cabang',
                'Status',
            ], ';');

            foreach ($customers as $c) {
                fputcsv($handle, [
                    $c->name,
                    $c->phone,
                    $c->email ?? '-',
                    $c->address ?? '-',
                    $c->total_rentals,
                    $c->total_transaction_value ? 'Rp ' . number_format((float) $c->total_transaction_value, 0, ',', '.') : 'Rp 0',
                    $c->last_rental_date ? \Carbon\Carbon::parse($c->last_rental_date)->format('d/m/Y') : '-',
                    $c->branch?->name ?? '-',
                    $c->is_blacklisted ? 'Blacklist' : 'Aktif',
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportCustomersPdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $search   = $request->input('search');

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $query = Customer::with(['branch', 'user'])
            ->select('customers.*')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('COUNT(*)')
                   ->whereColumn('rentals.customer_id', 'customers.id');
            }, 'total_rentals')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('SUM(total_amount)')
                   ->whereColumn('rentals.customer_id', 'customers.id')
                   ->whereIn('rentals.rental_status', ['returned', 'completed']);
            }, 'total_transaction_value')
            ->selectSub(function ($q) {
                $q->from('rentals')
                   ->selectRaw('MAX(rental_date)')
                   ->whereColumn('rentals.customer_id', 'customers.id');
            }, 'last_rental_date');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('last_rental_date')->get();

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $pdf = Pdf::loadView('reports.pdf.customers', [
            'customers'    => $customers,
            'branchName'   => $branchName,
            'search'       => $search,
            'generatedAt'  => now()->format('d/m/Y H:i'),
            'generatedBy'  => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-customer-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================================
    // LAPORAN PEMBAYARAN
    // =========================================================================

    public function payments(Request $request)
    {
        $branchId = $this->resolvedBranchId($request);
        $shared   = $this->sharedViewData($request);

        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');

        $query = Payment::with(['rental.customer', 'rental.branch', 'receivedBy']);

        if ($branchId !== null) {
            $query->whereHas('rental', fn($r) => $r->where('branch_id', $branchId));
        }

        if ($period === 'week') {
            $query->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('paid_at', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('paid_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('paid_at', '<=', $dateTo);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhereHas('rental', fn($r) => $r
                      ->where('invoice_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn($c) => $c
                          ->where('name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%")
                      )
                  );
            });
        }

        $payments = $query->latest('paid_at')->paginate(25)->withQueryString();

        $baseQuery = Payment::query();
        if ($branchId !== null) {
            $baseQuery->whereHas('rental', fn($r) => $r->where('branch_id', $branchId));
        }

        if ($period === 'week') {
            $baseQuery->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $baseQuery->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $baseQuery->whereDate('paid_at', today());
        } else {
            if ($dateFrom) {
                $baseQuery->whereDate('paid_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $baseQuery->whereDate('paid_at', '<=', $dateTo);
            }
        }

        $rentalPaymentNumbers = Payment::query()
            ->select('payment_number')
            ->whereIn('type', ['rental', 'deposit']);
        $finePaymentNumbers = Payment::query()
            ->select('payment_number')
            ->whereIn('type', ['late_fee', 'damage_fee']);

        $rentalReceived = (clone $baseQuery)->whereIn('type', ['rental', 'deposit'])->sum('amount')
            + (clone $baseQuery)->where('type', 'refund')
                ->whereIn('reference_number', $rentalPaymentNumbers)->sum('amount');
        $fineReceived = (clone $baseQuery)->whereIn('type', ['late_fee', 'damage_fee'])->sum('amount')
            + (clone $baseQuery)->where('type', 'refund')
                ->whereIn('reference_number', $finePaymentNumbers)->sum('amount');

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'total_amount' => (clone $baseQuery)->sum('amount'),
            'rental_received' => $rentalReceived,
            'fine_received' => $fineReceived,
        ];

        $outstandingFines = Rental::query()
            ->whereIn('fine_status', [Rental::FINE_UNPAID, Rental::FINE_PARTIAL])
            ->whereRaw('COALESCE(fine_amount, 0) > COALESCE(fine_paid_amount, 0)')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(COALESCE(fine_amount, 0) - COALESCE(fine_paid_amount, 0)), 0) AS aggregate')
            ->value('aggregate');

        $summary['outstanding_fines'] = (float) $outstandingFines;

        return view('reports.payments', array_merge($shared, [
            'payments'  => $payments,
            'summary'   => $summary,
            'period'    => $period,
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
            'search'    => $search,
        ]));
    }

    public function exportPaymentsExcel(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $query = Payment::with(['rental.customer', 'rental.branch', 'receivedBy']);

        if ($branchId !== null) {
            $query->whereHas('rental', fn($r) => $r->where('branch_id', $branchId));
        }

        if ($period === 'week') {
            $query->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('paid_at', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('paid_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('paid_at', '<=', $dateTo);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhereHas('rental', fn($r) => $r
                      ->where('invoice_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn($c) => $c
                          ->where('name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%")
                      )
                  );
            });
        }

        $payments = $query->latest('paid_at')->get();

        $branchLabel = $branchId
            ? (Branch::find($branchId)?->name ?? "cabang-{$branchId}")
            : 'semua-cabang';

        $filename = 'laporan-pembayaran-'
            . str($branchLabel)->slug()
            . '-' . now()->format('Ymd_His')
            . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Tanggal Bayar',
                'No. Pembayaran',
                'No. Invoice',
                'Pelanggan',
                'Cabang',
                'Jumlah',
                'Metode',
                'Tipe',
                'Catatan',
                'Diterima Oleh',
            ], ';');

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->paid_at?->format('d/m/Y H:i'),
                    $payment->payment_number,
                    $payment->rental?->invoice_number ?? '-',
                    $payment->rental?->customer?->name ?? '-',
                    $payment->rental?->branch?->name ?? '-',
                    $payment->amount,
                    $payment->method_label ?? $payment->method,
                    $payment->type,
                    $payment->notes ?? '-',
                    $payment->receivedBy?->name ?? '-',
                ], ';');
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportPaymentsPdf(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->resolvedBranchId($request);
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');

        if ($user->isSales()) {
            $branchId = (int) $user->branch_id;
        }

        $query = Payment::with(['rental.customer', 'rental.branch', 'receivedBy']);

        if ($branchId !== null) {
            $query->whereHas('rental', fn($r) => $r->where('branch_id', $branchId));
        }

        if ($period === 'week') {
            $query->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'today') {
            $query->whereDate('paid_at', today());
        } else {
            if ($dateFrom) {
                $query->whereDate('paid_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('paid_at', '<=', $dateTo);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhereHas('rental', fn($r) => $r
                      ->where('invoice_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn($c) => $c
                          ->where('name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%")
                      )
                  );
            });
        }

        $payments = $query->latest('paid_at')->get();

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? "Cabang #{$branchId}")
            : 'Semua Cabang';

        $periodLabel = match ($period) {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            default => ($dateFrom && $dateTo ? "{$dateFrom} s/d {$dateTo}" : 'Semua Periode'),
        };

        $pdf = Pdf::loadView('reports.pdf.payments', [
            'payments'    => $payments,
            'branchName'  => $branchName,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'generatedBy' => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-pembayaran-'
            . str($branchName)->slug()
            . '-' . now()->format('Ymd')
            . '.pdf';

        return $pdf->download($filename);
    }
}
