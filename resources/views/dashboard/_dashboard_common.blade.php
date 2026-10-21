@php
    $data = $data ?? [];
    $now = \Carbon\Carbon::now('Asia/Jakarta');
@endphp

@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $sales */
@endphp

<div id="dashboard-root" data-role="{{ $role ?? '' }}" class="space-y-6">

    <!-- HERO: Greeting + Today summary -->
    <div class="ds-card p-6">
        <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                @php
                    $greet = match(true) {
                        $now->hour < 11 => 'Selamat Pagi',
                        $now->hour < 15 => 'Selamat Siang',
                        $now->hour < 18 => 'Selamat Sore',
                        default => 'Selamat Malam',
                    };
                @endphp

                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="sparkles" class="h-4 w-4 text-primary"></i>
                        Utama
                    </span>
                    <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300"></i>
                    <span class="text-slate-500">Dashboard</span>
                </div>

                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-950">
                    {{ $greet }}, {{ auth()->user()->name }}
                </h2>

                <p class="mt-1 text-sm font-semibold text-slate-500">
                    {{ $now->translatedFormat('l, d M Y') }} • {{ $now->format('H:i') }} WIB
                </p>

                @if($role === 'admin_toko' || $role === 'sales')
                    <div class="mt-3 text-sm text-slate-600">
                        <span class="font-bold text-slate-900">Cabang:</span>
                        <span id="dashboard-branch-label" class="font-semibold">{{ $branches->firstWhere('id', $selectedBranchId ?? null)?->name ?? ($branches[0]->name ?? ($data['branch_name'] ?? '-')) }}</span>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap sm:items-end">
                @if($role === 'super_admin' || $role === 'admin_toko')
                    <div class="flex min-w-0 flex-col gap-1.5 sm:w-56">
                        <label for="filter-branch" class="text-xs font-semibold text-slate-600">Cabang</label>
                        <div class="relative min-w-0">
                        <select id="filter-branch" class="h-11 w-full min-w-0 appearance-none rounded-xl border border-slate-200 bg-white py-2 pl-3 pr-10 text-sm ds-transition">
                            @if($role === 'super_admin')
                                <option value="">Semua Cabang</option>
                            @endif
                            @foreach($branches ?? [] as $branch)
                                <option value="{{ $branch->id }}" {{ (string) ($selectedBranchId ?? '') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" aria-hidden="true" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>
                @endif

                @if($role !== 'sales')
                    <div class="flex min-w-0 flex-col gap-1.5 sm:w-56">
                        <label for="filter-sales" class="text-xs font-semibold text-slate-600">Sales</label>
                        <div class="relative min-w-0">
                        <select id="filter-sales" class="h-11 w-full min-w-0 appearance-none rounded-xl border border-slate-200 bg-white py-2 pl-3 pr-10 text-sm ds-transition">
                            <option value="">Semua Sales</option>
                            @foreach($sales ?? [] as $salesUser)
                                <option value="{{ $salesUser->id }}">{{ $salesUser->name }}</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" aria-hidden="true" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>
                @endif

                <span class="col-span-2 inline-flex w-fit items-center justify-self-start gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-extrabold tracking-wide text-blue-700 sm:col-span-1">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    Aktif
                </span>
            </div>
        </div>

        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="hidden" id="dashboard-subtitle">Memperbarui data secara real-time…</div>

            <div class="grid w-full grid-cols-2 items-stretch gap-2 sm:grid-cols-3 xl:grid-cols-5">
                @if($role === 'super_admin')
                    <a href="{{ route('branches.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="building-2" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Kelola Cabang</span>
                    </a>
                    <a href="{{ route('users.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="users" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Kelola Admin</span>
                    </a>
                    <a href="{{ route('broadcast-campaigns.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="send" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Broadcast</span>
                    </a>
                    <a href="{{ route('reports.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="trending-up" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Laporan Keuangan</span>
                    </a>
                    <a href="{{ route('rentals.scan') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="scan-line" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Scan QR</span>
                    </a>
                @endif

                @if($role === 'admin_toko')
                    <a href="{{ route('customers.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="user-plus" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Tambah Pelanggan</span>
                    </a>
                    <a href="{{ route('rentals.create') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="plus-circle" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Buat Sewa</span>
                    </a>
                    <a href="{{ route('rentals.index', ['status' => 'active']) }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="reply" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Pengembalian</span>
                    </a>
                    <a href="{{ route('broadcast-campaigns.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="send" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Broadcast</span>
                    </a>
                    <a href="{{ route('rentals.scan') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="scan-line" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Scan QR</span>
                    </a>
                @endif

                @if($role === 'sales')
                    <a href="{{ route('rentals.create') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="plus-circle" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Buat Sewa</span>
                    </a>
                    <a href="{{ route('customers.index') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="user" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Pelanggan Saya</span>
                    </a>
                    <a href="{{ route('rentals.scan') }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="scan-line" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Scan QR</span>
                    </a>
                    <a href="{{ route('rentals.index', ['status' => 'overdue']) }}" class="ds-hover-lift btn btn-secondary w-full text-center">
                        <i data-lucide="reply" class="h-4 w-4 text-primary"></i>
                        <span class="font-bold">Pengembalian</span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($role === 'admin_toko')
        <section id="sales-performance" class="space-y-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Performa Sales</h2>
                    <p id="performance-selection" class="text-sm text-slate-500">Pilih Sales untuk melihat performanya.</p>
                </div>
            </div>
            <p id="performance-empty" class="py-5 text-sm text-slate-500">Pilih cabang dan Sales untuk menampilkan performa.</p>
            <div id="performance-content" class="hidden space-y-4">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
                    <div class="border-l-2 border-blue-500 pl-3"><p class="text-xs text-slate-500">Transaksi</p><p id="performance-transactions" class="text-lg font-bold text-slate-900">0</p></div>
                    <div class="border-l-2 border-green-500 pl-3"><p class="text-xs text-slate-500">Selesai</p><p id="performance-completed" class="text-lg font-bold text-slate-900">0</p></div>
                    <div class="border-l-2 border-amber-500 pl-3"><p class="text-xs text-slate-500">Aktif</p><p id="performance-active" class="text-lg font-bold text-slate-900">0</p></div>
                    <div class="border-l-2 border-cyan-500 pl-3"><p class="text-xs text-slate-500">Pelanggan</p><p id="performance-customers" class="text-lg font-bold text-slate-900">0</p></div>
                    <div class="border-l-2 border-emerald-500 pl-3"><p class="text-xs text-slate-500">Pendapatan</p><p id="performance-revenue" class="text-lg font-bold text-slate-900">Rp 0</p></div>
                    <div class="border-l-2 border-rose-500 pl-3"><p class="text-xs text-slate-500">Pengembalian</p><p id="performance-returns" class="text-lg font-bold text-slate-900">0</p></div>
                    <div class="border-l-2 border-violet-500 pl-3"><p class="text-xs text-slate-500">Poin (akumulasi)</p><p id="performance-points" class="text-lg font-bold text-slate-900">0</p></div>
                </div>
                <div class="overflow-x-auto border-t border-slate-200">
                    <table class="w-full min-w-[1050px] text-left text-sm">
                        <thead><tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Cabang</th><th class="px-4 py-3">Nilai</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Petugas Pembayaran</th><th class="px-4 py-3">Petugas Pengembalian</th>
                        </tr></thead>
                        <tbody id="performance-rows" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        @php
            $cards = [
                ['label'=>'Pelanggan','icon'=>'users','stat'=>'total_customers','sub'=>'Aktif terdaftar','subKey'=>'customer_aktif','accent'=>'blue'],
                ['label'=>'Koleksi Produk','icon'=>'package','stat'=>'total_produk','sub'=>'Tersedia disewa','subKey'=>'produk_tersedia','accent'=>'amber'],
                ['label'=>'Sedang Disewa','icon'=>'shirt','stat'=>'produk_disewa','sub'=>'Jas sedang luar toko','subKey'=>null,'accent'=>'sky'],
                ['label'=>'Sewa Minggu Ini','icon'=>'calendar','stat'=>'penyewaan_minggu_ini','sub'=>'Transaksi baru minggu ini','subKey'=>null,'accent'=>'green'],
                ['label'=>'Pemasukan Hari Ini','icon'=>'wallet','stat'=>'pendapatan_hari_ini','sub'=>'Pemasukan kas hari ini','subKey'=>null,'accent'=>'emerald'],
            ];
        @endphp

        @foreach($cards as $c)
            <div class="ds-card p-3 sm:p-5 ds-transition ds-hover-lift group relative overflow-hidden">
                <div class="absolute inset-0 opacity-80" style="background:
                    radial-gradient(circle at 20% 0%, rgba(37,99,235,.12), transparent 45%),
                    radial-gradient(circle at 90% 10%, rgba(245,158,11,.10), transparent 40%);"></div>

                <div class="relative">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-extrabold uppercase tracking-widest text-slate-500">{{ $c['label'] }}</div>
                        </div>
                        <div class="h-9 w-9 sm:h-11 sm:w-11 rounded-2xl flex shrink-0 items-center justify-center text-white" style="background: linear-gradient(135deg, #2563EB, #60A5FA);">
                            <i data-lucide="{{ $c['icon'] }}" class="h-4 w-4 sm:h-5 sm:w-5"></i>
                        </div>
                    </div>

                    <div class="mt-3 sm:mt-4">
                        <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950" data-stat="{{ $c['stat'] }}">—</div>
                        <div class="mt-1 text-xs sm:text-sm font-semibold text-slate-500">
                            @if($c['subKey'])
                                <span class="text-slate-900" data-stat="{{ $c['subKey'] }}">—</span>
                            @endif
                            <span class="{{ $c['subKey'] ? 'ml-1' : '' }}">{{ $c['sub'] }}</span>
                        </div>
                        @if($c['stat']==='penyewaan_minggu_ini')
                            <div class="mt-3">
                                <div class="h-2 w-full rounded-full bg-blue-50 border border-blue-100 overflow-hidden">
                                    <div class="h-full w-2/3 rounded-full bg-blue-500/25"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Charts + Widgets -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="ds-card p-6 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Grafik Rental <span id="chart-rental-label">(30 Hari)</span></h3>
                    <p class="text-xs font-semibold text-slate-500">Tren aktivitas penyewaan</p>
                </div>
                <span class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">Live</span>
            </div>
            <div class="relative w-full h-[220px]">
                <canvas id="chartRentals"></canvas>
            </div>
        </div>

        <div class="ds-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Pemasukan <span id="chart-revenue-label">(30 Hari)</span></h3>
                    <p class="text-xs font-semibold text-slate-500">Omzet & performa</p>
                </div>
            </div>
            <div class="relative w-full h-[220px]">
                <canvas id="chartRevenue"></canvas>
            </div>
        </div>

        <div class="ds-card p-6 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Status Penyewaan</h3>
                    <p class="text-xs font-semibold text-slate-500">Menunggu, berjalan, terlambat</p>
                </div>
            </div>
            <div class="relative w-full h-[220px]">
                <canvas id="chartStatus"></canvas>
            </div>
        </div>

        <div class="ds-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Model Terlaris</h3>
                    <p class="text-xs font-semibold text-slate-500">Top produk</p>
                </div>
            </div>
            <div class="relative w-full h-[220px]">
                <canvas id="chartTopProducts"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

        <div class="ds-card p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-10 w-10 rounded-2xl flex items-center justify-center bg-amber-50 border border-amber-100">
                    <i data-lucide="receipt" class="h-5 w-5 text-amber-600"></i>
                </span>
                <h3 class="text-sm font-extrabold text-slate-900">Transaksi Terbaru</h3>
            </div>
            <div id="widgetTransactions" class="text-xs space-y-1"></div>
        </div>

        <div class="ds-card p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-10 w-10 rounded-2xl flex items-center justify-center bg-emerald-50 border border-emerald-100">
                    <i data-lucide="bell" class="h-5 w-5 text-emerald-600"></i>
                </span>
                <h3 class="text-sm font-extrabold text-slate-900">Jadwal Kembali</h3>
            </div>
            <div id="widgetReminders" class="text-xs space-y-1"></div>
        </div>

        <div class="ds-card p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-10 w-10 rounded-2xl flex items-center justify-center bg-red-50 border border-red-100">
                    <i data-lucide="alert-triangle" class="h-5 w-5 text-red-600"></i>
                </span>
                <h3 class="text-sm font-extrabold text-slate-900">Stok Menipis</h3>
            </div>
            <div id="widgetAlmostEmpty" class="text-xs space-y-1"></div>
        </div>
    </div>

    <!-- Empty State -->
    <div class="hidden" id="dashboard-empty" aria-live="polite">
        <div class="ds-card py-12">
            <div class="flex flex-col items-center justify-center text-center gap-2">
                <div class="h-14 w-14 rounded-3xl bg-blue-50 border border-blue-100 flex items-center justify-center">
                    <i data-lucide="inbox" class="h-7 w-7 text-blue-600"></i>
                </div>
                <h3 class="text-base font-extrabold text-slate-900">Belum ada aktivitas</h3>
                <p class="text-sm font-semibold text-slate-500 max-w-md">
                    Sistem sedang menyiapkan data. Coba jalankan aksi seperti scan QR atau buat sewa.
                </p>
                <a href="{{ route('rentals.scan') }}" class="mt-3 ds-hover-lift btn btn-primary">
                    <i data-lucide="scan-line" class="h-4 w-4"></i>
                    Scan QR Sekarang
                </a>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <div class="hidden" id="dashboard-error" aria-live="polite">
        <div class="ds-card py-10 border border-red-200 bg-red-50">
            <div class="flex flex-col items-center justify-center text-center gap-2">
                <div class="h-14 w-14 rounded-3xl bg-white border border-red-100 flex items-center justify-center">
                    <i data-lucide="alert-circle" class="h-7 w-7 text-red-600"></i>
                </div>
                <h3 class="text-base font-extrabold text-slate-900">Gagal memuat dashboard</h3>
                <p class="text-sm font-semibold text-slate-600">Silakan coba sesaat lagi.</p>
                <button type="button" onclick="window.location.reload()" class="ds-hover-lift btn btn-danger">
                    Muat Ulang
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const DASHBOARD_SALES_DATA = @json($sales ?? []);
</script>

<script>
    const DASHBOARD_POLL_INTERVAL_MS = 10000;

    const dashboardCharts = {
        rentals: null,
        revenue: null,
        status: null,
        topProducts: null,
    };

    let firstLoadDone = false;
    let currentAbortController = null;

    function formatCurrencyIDR(value) {
        const num = Number(value);
        if (value === null || value === undefined || Number.isNaN(num)) return 'Rp 0';
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    function $(id) {
        return document.getElementById(id);
    }

    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function showLoadingFirstTime() {
        const root = $('dashboard-root');
        if (!root) return;

        root.querySelectorAll('[id^="widget"]').forEach(el => {
            if (!el) return;
            el.innerHTML = `
                <div class="animate-pulse space-y-2">
                    <div class="h-3 bg-slate-200/70 rounded-full w-4/5"></div>
                    <div class="h-3 bg-slate-200/70 rounded-full w-3/5"></div>
                </div>
            `;
        });
    }

    function hideLoadingFirstTime() {
        // no-op
    }

    function showErrorState() {
        const err = $('dashboard-error');
        if (err) err.classList.remove('hidden');
    }

    function hideErrorState() {
        const err = $('dashboard-error');
        if (err) err.classList.add('hidden');
    }

    function hideEmptyState() {
        const emptyEl = $('dashboard-empty');
        if (emptyEl) emptyEl.classList.add('hidden');
    }

    function showEmptyState() {
        const emptyEl = $('dashboard-empty');
        if (emptyEl) emptyEl.classList.remove('hidden');
    }

    function setDashboardSubtitle(generatedAt) {
        const subtitle = $('dashboard-subtitle');
        if (!subtitle) return;
        if (generatedAt) {
            subtitle.textContent = 'Pembaruan Terakhir: ' + new Date(generatedAt).toLocaleTimeString('id-ID');
        }
    }

    function renderList(targetId, items, renderFn) {
        const el = $(targetId);
        if (!el) return;

        const arr = Array.isArray(items) ? items : [];
        if (arr.length === 0) {
            el.innerHTML = '<div class="text-xs text-slate-400 py-1 font-medium">Belum ada data</div>';
            return;
        }

        el.innerHTML = arr.map(renderFn).join('');
    }

    function initChartsIfNeeded() {
        const ChartRef = window.Chart;
        if (!ChartRef) return;

        const ctxRentals = $('chartRentals');
        const ctxRevenue = $('chartRevenue');
        const ctxStatus = $('chartStatus');
        const ctxTopProducts = $('chartTopProducts');

        const fontConfig = {
            family: "'Inter', system-ui, sans-serif",
            size: 11
        };

        const tooltipBase = {
            backgroundColor: 'rgba(15,23,42,.92)',
            borderColor: 'rgba(148,163,184,.25)',
            borderWidth: 1,
            titleColor: '#fff',
            bodyColor: '#E2E8F0',
            padding: 10,
            displayColors: false,
            cornerRadius: 12,
            callbacks: {
                label: function(context) {
                    const v = context.parsed?.y ?? context.parsed ?? 0;
                    return String(v);
                }
            }
        };

        const makeGradient = (ctx, area) => {
            // Chart.js v4: ctx.chart.ctx exists, but we have canvas ctx already.
            const g = ctx.createLinearGradient(0, 0, 0, area);
            return g;
        };

        const gradRentals = ctxRentals ? ctxRentals.getContext('2d').createLinearGradient(0, 0, 0, 260) : null;
        if (gradRentals) {
            gradRentals.addColorStop(0, 'rgba(37,99,235,.35)');
            gradRentals.addColorStop(1, 'rgba(37,99,235,0)');
        }

        const gradRevenue = ctxRevenue ? ctxRevenue.getContext('2d').createLinearGradient(0, 0, 0, 260) : null;
        if (gradRevenue) {
            gradRevenue.addColorStop(0, 'rgba(96,165,250,.30)');
            gradRevenue.addColorStop(1, 'rgba(96,165,250,0)');
        }

        if (!dashboardCharts.rentals && ctxRentals) {
            dashboardCharts.rentals = new ChartRef(ctxRentals.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Rental',
                        data: [],
                        borderColor: '#2563EB',
                        backgroundColor: gradRentals || 'rgba(37,99,235,.12)',
                        fill: true,
                        tension: 0.45,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#2563EB',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        cubicInterpolationMode: 'monotone',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipBase,
                            mode: 'index',
                            intersect: false,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const v = context.parsed?.y ?? context.parsed ?? 0;
                                    return `Rental: ${v}`;
                                }
                            }
                        }
                    },
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grace: '5%',
                            ticks: { font: fontConfig, color: '#64748B' },
                            grid: { color: 'rgba(226,232,240,.8)' }
                        },
                        x: {
                            ticks: { font: fontConfig, color: '#64748B' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        if (!dashboardCharts.revenue && ctxRevenue) {
            dashboardCharts.revenue = new ChartRef(ctxRevenue.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Omzet',
                        data: [],
                        borderColor: '#60A5FA',
                        backgroundColor: gradRevenue || 'rgba(96,165,250,.14)',
                        fill: true,
                        tension: 0.45,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#60A5FA',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        cubicInterpolationMode: 'monotone',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipBase,
                            mode: 'index',
                            intersect: false,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const v = context.parsed?.y ?? context.parsed ?? 0;
                                    return `Omzet: ${v}`;
                                }
                            }
                        }
                    },
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grace: '5%',
                            ticks: { font: fontConfig, color: '#64748B' },
                            grid: { color: 'rgba(226,232,240,.8)' }
                        },
                        x: {
                            ticks: { font: fontConfig, color: '#64748B' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        if (!dashboardCharts.status && ctxStatus) {
            dashboardCharts.status = new ChartRef(ctxStatus.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Menunggu', 'Berjalan', 'Terlambat', 'Kembali'],
                    datasets: [{
                        label: 'Status',
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            'rgba(96,165,250,.85)',
                            'rgba(34,197,94,.85)',
                            'rgba(239,68,68,.85)',
                            'rgba(245,158,11,.85)'
                        ],
                        borderRadius: 8,
                        borderSkipped: false,
                        hoverBackgroundColor: [
                            'rgba(96,165,250,1)',
                            'rgba(34,197,94,1)',
                            'rgba(239,68,68,1)',
                            'rgba(245,158,11,1)'
                        ],
                        barPercentage: 0.6,
                        categoryPercentage: 0.7,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipBase,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const v = context.parsed?.y ?? context.parsed ?? 0;
                                    return `Jumlah: ${v}`;
                                }
                            }
                        }
                    },
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: fontConfig,
                                color: '#64748B',
                                stepSize: 1,
                            },
                            grid: { color: 'rgba(226,232,240,.8)' }
                        },
                        x: {
                            ticks: { font: fontConfig, color: '#64748B' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        if (!dashboardCharts.topProducts && ctxTopProducts) {
            const colors = ['#2563EB', '#60A5FA', '#22C55E', '#F59E0B', '#EF4444'];
            dashboardCharts.topProducts = new ChartRef(ctxTopProducts.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: colors,
                        borderWidth: 3,
                        borderColor: 'rgba(255,255,255,1)',
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            align: 'center',
                            labels: {
                                boxWidth: 12,
                                boxHeight: 8,
                                padding: 14,
                                font: fontConfig,
                                color: '#64748B'
                            }
                        },
                        tooltip: {
                            ...tooltipBase,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(ctx) {
                                    const label = ctx.label ?? '';
                                    const val = ctx.parsed ?? 0;
                                    return label ? `${label}: ${val}` : String(val);
                                }
                            }
                        }
                    },
                    cutout: '62%'
                }
            });
        }
    }

    function updateCharts(charts) {
        const chartsData = isObject(charts) ? charts : {};

        const rentals30 = Array.isArray(chartsData.rentals_30_days) ? chartsData.rentals_30_days : [];
        const rentalsLabels = rentals30.map(x => x?.date).filter(Boolean);
        const rentalsValues = rentals30.map(x => Number(x?.count ?? 0));

        if (dashboardCharts.rentals) {
            dashboardCharts.rentals.data.labels = rentalsLabels;
            if (dashboardCharts.rentals.data.datasets[0]) {
                dashboardCharts.rentals.data.datasets[0].data = rentalsValues;
            }
            dashboardCharts.rentals.update();
        }

        const omzet30 = Array.isArray(chartsData.omzet_30_days) ? chartsData.omzet_30_days : [];
        const omzetLabels = omzet30.map(x => x?.date).filter(Boolean);
        const omzetValues = omzet30.map(x => Number(x?.revenue ?? 0));

        if (dashboardCharts.revenue) {
            dashboardCharts.revenue.data.labels = omzetLabels;
            if (dashboardCharts.revenue.data.datasets[0]) {
                dashboardCharts.revenue.data.datasets[0].data = omzetValues;
            }
            dashboardCharts.revenue.update();
        }

        const status = isObject(chartsData.status_rental) ? chartsData.status_rental : {};
        const statusValues = [
            Number(status?.waiting ?? 0),
            Number(status?.active ?? 0),
            Number(status?.overdue ?? 0),
            Number(status?.returned ?? 0),
        ];

        if (dashboardCharts.status) {
            if (dashboardCharts.status.data.datasets[0]) {
                dashboardCharts.status.data.datasets[0].data = statusValues;
            }
            dashboardCharts.status.update();
        }

        const top = Array.isArray(chartsData.top_products) ? chartsData.top_products : [];
        const topLabels = top.map(x => x?.name).filter(Boolean);
        const topValues = top.map(x => Number(x?.count ?? 0));

        if (dashboardCharts.topProducts) {
            dashboardCharts.topProducts.data.labels = topLabels;
            if (dashboardCharts.topProducts.data.datasets[0]) {
                dashboardCharts.topProducts.data.datasets[0].data = topValues;
            }
            dashboardCharts.topProducts.update();
        }
    }

    function updateStats(stats) {
        const statsData = isObject(stats) ? stats : {};

        document.querySelectorAll('[data-stat]').forEach(node => {
            if (!node) return;
            const key = node.getAttribute('data-stat');
            if (!key) return;

            const value = statsData[key];
            const isRevenueKey = key.includes('pendapatan');
            if (isRevenueKey) {
                node.textContent = formatCurrencyIDR(value);
                return;
            }

            node.textContent = (value === null || value === undefined) ? '0' : String(value);
        });
    }

    function updateWidgets(widgets) {
        const w = isObject(widgets) ? widgets : {};

        renderList('widgetTransactions', w.transaksi_terbaru, (it) => {
            return `
                <div class="py-2 border-b last:border-b-0 border-slate-100">
                    <p class="text-xs font-extrabold text-slate-900">${it?.invoice ?? ''}</p>
                    <p class="text-[11px] font-semibold text-slate-500 mt-0.5">${it?.customer ?? ''} &middot; <span class="text-primary">${it?.status ?? ''}</span></p>
                </div>
            `;
        });

        const today = new Date();
        today.setHours(0,0,0,0);
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);

        const remindersArr = Array.isArray(w.reminder_pengembalian) ? w.reminder_pengembalian : [];
        const groups = { today: [], tomorrow: [], overdue: [] };

        const parseDue = (v) => {
            if (!v) return null;
            const d = new Date(v);
            return isNaN(d.getTime()) ? null : d;
        };

        remindersArr.forEach((it) => {
            const due = parseDue(it?.return_due_date ?? it?.due_date ?? it?.due ?? null);
            if (!due) {
                groups.today.push(it);
                return;
            }
            due.setHours(0,0,0,0);
            if (due < today) groups.overdue.push(it);
            else if (due >= today && due < tomorrow) groups.today.push(it);
            else if (due >= tomorrow) {
                if (due.getTime() === tomorrow.getTime()) groups.tomorrow.push(it);
                else groups.overdue.push(it);
            }
        });

        const renderGroup = (arr, style, title) => {
            if (!Array.isArray(arr) || arr.length === 0) {
                return '';
            }

            return `
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border ${style.border} bg-${style.bg} text-${style.text}">
                        <span class="h-2 w-2 rounded-full ${style.dot}"></span>
                        <span class="text-[11px] font-extrabold">${title}</span>
                    </div>
                    ${arr.map((it) => {
                        const inv = it?.invoice ?? it?.invoice_number ?? '';
                        const due = it?.return_due_date ?? '';
                        return `
                            <div class="flex items-start gap-2 py-2 border-b last:border-b-0 border-slate-100">
                                <i data-lucide="clock" class="h-4 w-4 ${style.icon} mt-0.5"></i>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[12px] font-extrabold text-slate-900 truncate">${inv}</p>
                                    <p class="text-[11px] font-semibold text-slate-500 mt-0.5">Jatuh Tempo: <span class="text-slate-900">${due}</span></p>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        };

        const styleToday = { border:'border-blue-100', bg:'blue-50', text:'text-blue-700', dot:'bg-blue-500', icon:'text-blue-600' };
        const styleTomorrow = { border:'border-amber-100', bg:'amber-50', text:'text-amber-700', dot:'bg-amber-500', icon:'text-amber-600' };
        const styleOverdue = { border:'border-red-100', bg:'red-50', text:'text-red-700', dot:'bg-red-500', icon:'text-red-600' };

        const widget = $('widgetReminders');
        if (widget) {
            const allEmpty = groups.today.length === 0 && groups.tomorrow.length === 0 && groups.overdue.length === 0;

            if (allEmpty) {
                widget.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-6 text-center gap-2">
                        <div class="h-10 w-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center">
                            <i data-lucide="inbox" class="h-5 w-5 text-emerald-500"></i>
                        </div>
                        <p class="text-xs font-extrabold text-slate-900">Belum ada pesanan yang mau dikembalikan</p>
                        <p class="text-[11px] font-semibold text-slate-500">Semua jas sudah dikembalikan tepat waktu</p>
                    </div>
                `;
            } else {
                widget.innerHTML = `
                    <div class="space-y-3">
                        ${renderGroup(groups.today, styleToday, 'Hari Ini')}
                        ${renderGroup(groups.tomorrow, styleTomorrow, 'Besok')}
                        ${renderGroup(groups.overdue, styleOverdue, 'Terlambat')}
                    </div>
                `;
            }
        }

        // Almost empty: keep lightweight mapping
        renderList('widgetAlmostEmpty', w.produk_hampir_habis, (p) => {
            const stock = Number(p?.stock_available ?? 0);
            const pct = Math.min(100, (stock / 3) * 100);
            return `
                <div class="py-2 border-b last:border-b-0 border-slate-100">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12px] font-extrabold text-slate-900 truncate">${p?.name ?? ''}</p>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-50 border border-amber-100 text-amber-700 font-extrabold">${stock}</span>
                    </div>
                    <div class="mt-2 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-400 rounded-full" style="width:${pct}%;"></div>
                    </div>
                </div>
            `;
        });
    }

    function normalizePayload(response) {
        const data = isObject(response) ? response : {};
        return {
            stats: data.stats ?? {},
            charts: data.charts ?? {},
            widgets: data.widgets ?? {},
            performance: data.performance ?? null,
            meta: data.meta ?? {},
        };
    }

    async function fetchDashboardData() {
        if (currentAbortController) currentAbortController.abort();
        currentAbortController = new AbortController();

        const params = new URLSearchParams();
        const branchFilter = document.getElementById('filter-branch');
        const salesFilter = document.getElementById('filter-sales');
        if (branchFilter && branchFilter.value) params.append('branch_id', branchFilter.value);
        if (salesFilter && salesFilter.value) params.append('sales_user_id', salesFilter.value);

        const url = '{{ route('dashboard.data') }}' + (params.toString() ? '?' + params.toString() : '');

        const res = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: currentAbortController.signal,
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        const chartLabel = data?.charts?.chart_label ?? '30 Hari';
        const rentalLabel = document.getElementById('chart-rental-label');
        const revenueLabel = document.getElementById('chart-revenue-label');
        if (rentalLabel) rentalLabel.textContent = '(' + chartLabel + ')';
        if (revenueLabel) revenueLabel.textContent = '(' + chartLabel + ')';

        return data;
    }

    function hasAnyData(stats, charts, widgets) {
        const s = isObject(stats) ? stats : {};
        const c = isObject(charts) ? charts : {};
        const w = isObject(widgets) ? widgets : {};

        const statsKeys = [
            'total_customers', 'customer_aktif', 'total_produk', 'produk_tersedia', 'produk_disewa',
            'total_penyewaan', 'penyewaan_hari_ini', 'penyewaan_minggu_ini', 'penyewaan_bulan_ini',
            'pendapatan_hari_ini', 'invoice_hari_ini', 'receipt_hari_ini', 'pengembalian_hari_ini',
        ];

        const statsAny = statsKeys.some(k => Number(s[k] ?? 0) > 0);

        const chartsAny = (
            (Array.isArray(c.rentals_30_days) && c.rentals_30_days.length > 0) ||
            (Array.isArray(c.omzet_30_days) && c.omzet_30_days.length > 0) ||
            (Array.isArray(c.top_products) && c.top_products.length > 0)
        );

        const widgetsAny = (
            (Array.isArray(w.transaksi_terbaru) && w.transaksi_terbaru.length > 0) ||
            (Array.isArray(w.reminder_pengembalian) && w.reminder_pengembalian.length > 0) ||
            (Array.isArray(w.produk_hampir_habis) && w.produk_hampir_habis.length > 0)
        );

        return statsAny || chartsAny || widgetsAny;
    }

    function updateDashboard(payload) {
        const normalized = normalizePayload(payload);
        const stats = normalized.stats ?? {};
        const charts = normalized.charts ?? {};
        const widgets = normalized.widgets ?? {};

        hideErrorState();
        updateStats(stats);
        updatePerformance(normalized.performance);

        initChartsIfNeeded();
        updateCharts(charts);
        updateWidgets(widgets);

        const meta = normalized.meta ?? {};
        setDashboardSubtitle(meta.generated_at);

        const empty = !hasAnyData(stats, charts, widgets);
        if (empty) showEmptyState();
        else hideEmptyState();
    }

    function handleError() { showErrorState(); }

    function updatePerformance(performance) {
        const content = document.getElementById('performance-content');
        if (!content) return;

        const empty = document.getElementById('performance-empty');
        const selection = document.getElementById('performance-selection');
        if (!performance) {
            content.classList.add('hidden');
            empty.classList.remove('hidden');
            selection.textContent = 'Pilih Sales untuk melihat performanya.';
            return;
        }

        empty.classList.add('hidden');
        content.classList.remove('hidden');
        selection.textContent = `Sales: ${performance.sales} | Cabang: ${performance.branch ?? '-'}`;

        const values = {
            'performance-transactions': performance.transactions,
            'performance-completed': performance.completed,
            'performance-active': performance.active,
            'performance-customers': performance.customers,
            'performance-revenue': formatCurrencyIDR(performance.revenue),
            'performance-returns': performance.returns,
            'performance-points': performance.points,
        };
        Object.entries(values).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value ?? 0;
        });

        const body = document.getElementById('performance-rows');
        if (!body) return;
        body.replaceChildren();

        const labels = {
            waiting: 'Menunggu',
            active: 'Aktif',
            overdue: 'Terlambat',
            returned: 'Dikembalikan',
            cancelled: 'Dibatalkan',
        };
        (performance.rows ?? []).forEach(item => {
            const row = document.createElement('tr');
            const cells = [
                item.date ?? '-',
                item.invoice ?? '-',
                item.customer ?? '-',
                item.branch ?? '-',
                formatCurrencyIDR(item.amount),
                item.status_label ?? labels[item.status] ?? item.status ?? '-',
                item.payment_operator ?? '-',
                item.return_operator ?? '-',
            ];
            cells.forEach((value, index) => {
                const cell = document.createElement('td');
                cell.className = 'px-4 py-3';
                cell.textContent = value;
                row.appendChild(cell);
            });
            body.appendChild(row);
        });

        if ((performance.rows ?? []).length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'py-5 text-center text-slate-500';
            cell.textContent = 'Tidak ada transaksi pada filter ini.';
            row.appendChild(cell);
            body.appendChild(row);
        }
    }

    function filterSalesDropdownByBranch() {
        const branchFilter = document.getElementById('filter-branch');
        const salesFilter = document.getElementById('filter-sales');
        if (!branchFilter) return;

        const selectedBranch = branchFilter.value;
        const branchLabel = document.getElementById('dashboard-branch-label');
        if (branchLabel) {
            branchLabel.textContent = selectedBranch
                ? branchFilter.selectedOptions[0]?.textContent ?? '-'
                : (branchFilter.options[0]?.textContent ?? 'Semua Cabang');
        }

        if (!salesFilter) return;
        const firstOption = salesFilter.querySelector('option[value=""]');
        if (!firstOption) return;

        salesFilter.innerHTML = '';
        salesFilter.appendChild(firstOption);

        const matches = DASHBOARD_SALES_DATA.filter(s => !selectedBranch || String(s.branch_id) === String(selectedBranch));
        matches.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            salesFilter.appendChild(opt);
        });

        salesFilter.value = '';
    }

    function initDashboard() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        const tick = async () => {
            try {
                if (!firstLoadDone) {
                    showLoadingFirstTime();
                }
                const payload = await fetchDashboardData();
                updateDashboard(payload);
                if (!firstLoadDone) {
                    firstLoadDone = true;
                }
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                handleError();
            }
        };

        const branchFilter = document.getElementById('filter-branch');
        const salesFilter = document.getElementById('filter-sales');
        if (branchFilter) branchFilter.addEventListener('change', () => {
            filterSalesDropdownByBranch();
            tick();
        });
        if (salesFilter) salesFilter.addEventListener('change', tick);
        filterSalesDropdownByBranch();
        tick();
        setInterval(tick, DASHBOARD_POLL_INTERVAL_MS);
    }

    document.addEventListener('DOMContentLoaded', initDashboard);
</script>
