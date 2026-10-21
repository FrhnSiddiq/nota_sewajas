{{-- resources/views/reports/partials/activity-log-content.blade.php --}}

{{-- SUMMARY CARDS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div class="card p-4 flex items-center gap-4" style="min-height: 100px">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: var(--secondary)">
            <i data-lucide="scroll-text" class="w-5 h-5" style="color: var(--primary)"></i>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-soft)">Total Aktivitas</p>
            <p class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">{{ $summaryTotal }}</p>
        </div>
    </div>
    <div class="card p-4 flex items-center gap-4" style="min-height: 100px">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:#EFF6FF">
            <i data-lucide="clock" class="w-5 h-5" style="color:#1D4ED8"></i>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-soft)">Aktivitas Hari Ini</p>
            <p class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">{{ $summaryToday }}</p>
        </div>
    </div>
    <div class="card p-4 flex items-center gap-4" style="min-height: 100px">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:#F0FDF4">
            <i data-lucide="users" class="w-5 h-5" style="color:#15803D"></i>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-soft)">Pengguna Aktif</p>
            <p class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">{{ $summaryActiveUsers }}</p>
        </div>
    </div>
</div>

{{-- TABS + FILTER --}}
<div class="ds-card overflow-hidden mb-4">
    {{-- TAB NAVIGATION --}}
    <div class="flex items-center gap-1 px-4 pt-3 overflow-x-auto" style="border-bottom: 1px solid var(--border)">
        @foreach($tabs as $key => $label)
            @php
                $isActive = ($tab ?? 'all') === $key;
                $queryParams = request()->except('tab');
                $queryParams['tab'] = $key;
            @endphp
            <a href="{{ route('reports.activity-log', $queryParams) }}"
               class="px-4 py-2.5 text-sm font-medium whitespace-nowrap ds-transition rounded-t-lg"
               style="color: {{ $isActive ? 'var(--primary)' : 'var(--text-soft)' }};
                      background: {{ $isActive ? 'var(--hover)' : 'transparent' }};
                      border-bottom: 2px solid {{ $isActive ? 'var(--primary)' : 'transparent' }};
                      margin-bottom: -1px;">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- FILTER PANEL --}}
    <form method="GET" action="{{ route('reports.activity-log') }}" class="p-4">
        <input type="hidden" name="tab" value="{{ $tab ?? 'all' }}">
        <div class="grid grid-cols-1 lg:grid-cols-[160px_140px_140px_150px_180px_minmax(200px,1fr)] gap-4 items-end">
            @if($isSuperAdmin)
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-soft)">Cabang</label>
                    <select name="branch_id" class="form-input">
                        <option value="">Semua Cabang</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ (string) $selectedBranchId === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-soft)">Periode</label>
                <select name="period" class="form-input" onchange="toggleDateFields(this)">
                    <option value="">Pilih Tanggal</option>
                    <option value="today" {{ ($period ?? '') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="week" {{ ($period ?? '') === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="month" {{ ($period ?? '') === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-soft)">Dari Tanggal</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom ?? '' }}" class="form-input">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-soft)">Sampai Tanggal</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo ?? '' }}" class="form-input">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-soft)">Aksi</label>
                <select name="action" class="form-input">
                    <option value="">Semua Aksi</option>
                    @foreach($actionOptions as $opt)
                        <option value="{{ $opt['value'] }}" {{ ($action ?? '') === $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-soft)">Cari</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Cari deskripsi, user, model..." class="form-input">
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-3">
            <button type="submit" class="btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
            </button>
            <a href="{{ route('reports.activity-log', array_filter(['tab' => $tab ?? 'all', 'branch_id' => $isSuperAdmin ? $selectedBranchId : null])) }}" class="btn-secondary">Reset</a>
            @php
                $exportParams = [
                    'tab' => $tab ?? 'all',
                    'period' => $period ?? '',
                    'date_from' => $dateFrom ?? '',
                    'date_to' => $dateTo ?? '',
                    'action' => $action ?? '',
                    'search' => $search ?? '',
                    'branch_id' => $selectedBranchId,
                ];
            @endphp
            <a href="{{ route('reports.activity-log.export.csv', $exportParams) }}" class="btn-secondary">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
            <a href="{{ route('reports.activity-log.export.pdf', $exportParams) }}" class="btn-secondary">
                <i data-lucide="file-text" class="w-4 h-4"></i> Export PDF
            </a>
        </div>
    </form>
</div>

{{-- TABLE --}}
<div class="ds-card overflow-hidden">
    <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color:var(--border)">
        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">
            <i data-lucide="scroll-text" class="w-4 h-4 inline mr-2" style="color:var(--primary)"></i>
            Riwayat Aktivitas
        </h2>
        <span class="text-xs" style="color:var(--text-soft)">{{ $logs->total() }} entri</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 155px">
                <col style="width: 140px">
                <col style="width: 100px">
                <col style="width: 150px">
                <col style="width: auto">
                <col style="width: auto">
                <col style="width: 100px">
                <col style="width: 70px">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Waktu</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">User</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Cabang</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Aksi</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Deskripsi</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Detail Perubahan</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Model</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">ID</th>
                </tr>
            </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $actionLabels = [
                            'create_rental' => 'Buat Sewa',
                            'rental_deleted' => 'Batalkan Sewa',
                            'handover_rental' => 'Selesaikan Sewa',
                            'return_rental' => 'Selesaikan Sewa',
                            'recalculate_payment_status' => 'Ubah Sewa',
                            'update_transaction' => 'Ubah Sewa',
                            'delete_transaction' => 'Batalkan Sewa',
                            'process_payment' => 'Terima Pembayaran',
                            'record_refund' => 'Batalkan Pembayaran',
                            'create_payment' => 'Terima Pembayaran',
                            'update_payment' => 'Ubah Pembayaran',
                            'delete_payment' => 'Batalkan Pembayaran',
                            'void_payment' => 'Batalkan Pembayaran',
                            'refund_payment' => 'Batalkan Pembayaran',
                            'create_customer' => 'Tambah Pelanggan',
                            'update_customer' => 'Ubah Data Pelanggan',
                            'delete_customer' => 'Hapus Pelanggan',
                            'soft_delete_customer' => 'Hapus Pelanggan',
                            'force_delete_customer' => 'Hapus Pelanggan',
                            'restore_customer' => 'Pulihkan Pelanggan',
                            'create_product' => 'Tambah Produk',
                            'update_product' => 'Ubah Produk',
                            'update_product_stock' => 'Ubah Stok',
                            'delete_product' => 'Hapus Produk',
                            'create_user' => 'Tambah Pengguna',
                            'update_user' => 'Ubah Pengguna',
                            'delete_user' => 'Hapus Pengguna',
                            'activate_user' => 'Aktifkan Pengguna',
                            'deactivate_user' => 'Nonaktifkan Pengguna',
                        ];

                        $featureLabels = [
                            'create_rental' => 'Penyewaan',
                            'rental_deleted' => 'Penyewaan',
                            'handover_rental' => 'Penyewaan',
                            'recalculate_payment_status' => 'Penyewaan',
                            'return_rental' => 'Pengembalian',
                            'process_payment' => 'Pembayaran',
                            'record_refund' => 'Pembayaran',
                            'create_payment' => 'Pembayaran',
                            'update_payment' => 'Pembayaran',
                            'delete_payment' => 'Pembayaran',
                            'void_payment' => 'Pembayaran',
                            'refund_payment' => 'Pembayaran',
                            'delete_customer' => 'Pelanggan',
                            'soft_delete_customer' => 'Pelanggan',
                            'restore_customer' => 'Pelanggan',
                            'force_delete_customer' => 'Pelanggan',
                            'activate_user' => 'Pengguna',
                            'deactivate_user' => 'Pengguna',
                            'delete_user' => 'Pengguna',
                            'update_product_stock' => 'Produk',
                            'update_transaction' => 'Transaksi',
                            'delete_transaction' => 'Transaksi',
                        ];
                        $currentFeature = null;
                    @endphp
                @forelse($logs as $log)
                    @php
                        $featureKey = null;
                        foreach ($featureLabels as $prefix => $label) {
                            if (str_starts_with($log->action, $prefix)) {
                                $featureKey = $label;
                                break;
                            }
                        }
                        if (!$featureKey) {
                            $featureKey = 'Lainnya';
                        }

                        $badgeClass = 'badge-gray';
                        if (str_starts_with($log->action, 'create_')) $badgeClass = 'badge-green';
                        elseif (str_starts_with($log->action, 'update_')) $badgeClass = 'badge-blue';
                        elseif (str_starts_with($log->action, 'activate_')) $badgeClass = 'badge-green';
                        elseif (str_starts_with($log->action, 'deactivate_')) $badgeClass = 'badge-yellow';
                        elseif (str_starts_with($log->action, 'delete_') || str_starts_with($log->action, 'force_delete_') || str_starts_with($log->action, 'soft_delete_')) $badgeClass = 'badge-red';
                        elseif (str_starts_with($log->action, 'process_payment') || str_starts_with($log->action, 'record_refund') || str_starts_with($log->action, 'create_payment') || str_starts_with($log->action, 'void_payment') || str_starts_with($log->action, 'refund_payment')) $badgeClass = 'badge-blue';
                        elseif (str_starts_with($log->action, 'return_rental')) $badgeClass = 'badge-green';
                        elseif (str_starts_with($log->action, 'handover_rental')) $badgeClass = 'badge-blue';
                        elseif (str_starts_with($log->action, 'recalculate_payment_status')) $badgeClass = 'badge-gray';
                        elseif (str_starts_with($log->action, 'restore_')) $badgeClass = 'badge-green';

                        $oldValues = \App\Services\ActivityLogFormatter::normalizeValues($log->old_values);
                        $newValues = \App\Services\ActivityLogFormatter::normalizeValues($log->new_values);
                        $changes = \App\Services\ActivityLogFormatter::getDisplayableChanges(
                            $log->action,
                            $log->model_type,
                            $oldValues,
                            $newValues
                        );
                    @endphp
                    @if($featureKey !== $currentFeature)
                        @php $currentFeature = $featureKey; @endphp
                        <tr>
                            <td colspan="8" class="px-4 py-2 bg-slate-50" style="border-bottom: 1px solid var(--border); height: 36px">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $currentFeature }}</span>
                            </td>
                        </tr>
                    @endif
                    <tr class="hover:bg-blue-50/40 ds-transition" style="min-height: 52px">
                        <td class="px-4 py-3 text-sm align-middle whitespace-nowrap" style="color:var(--text-soft)">
                            {{ $log->created_at?->format('d M Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm align-middle">
                            <p class="text-sm font-medium" style="color:var(--text-dark)">{{ $log->user?->name ?? '-' }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm align-middle" style="color:var(--text-soft)">
                            {{ ($log->model_type === \App\Models\Rental::class ? $log->rental?->branch?->name : null) ?? $log->branch?->name ?? '-' }}
                        </td>
                            <td class="px-4 py-3 text-sm align-middle">
                                <span class="badge {{ $badgeClass }} text-[10px]">{{ $actionLabels[$log->action] ?? $log->action }}</span>
                            </td>
                        <td class="px-4 py-3 text-sm align-middle">
                            <p class="text-sm" style="color:var(--text-dark)" title="{{ $log->description }}">{{ $log->description }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm align-middle">
                            @if(!empty($changes))
                                <div class="flex flex-col gap-1 text-xs">
                                    @foreach(array_slice($changes, 0, 4) as $change)
                                        @php
                                            $fieldLabel = match($change['field']) {
                                                'stock_available' => 'Stok tersedia',
                                                'stock_total' => 'Stok total',
                                                'stock_note' => 'Catatan stok',
                                                'rental_status' => 'Status penyewaan',
                                                'payment_status' => 'Status pembayaran',
                                                'fine_status' => 'Status denda',
                                                'paid_amount' => 'Total dibayar',
                                                'remaining_amount' => 'Sisa tagihan',
                                                'late_fee' => 'Denda',
                                                'fine_amount' => 'Total denda',
                                                'fine_paid_amount' => 'Denda dibayar',
                                                'total_amount' => 'Total',
                                                'subtotal' => 'Subtotal',
                                                'discount' => 'Diskon',
                                                'deposit' => 'Deposit',
                                                'change_amount' => 'Kembalian',
                                                'price' => 'Harga',
                                                'price_per_day' => 'Harga/hari',
                                                'quantity' => 'Kuantitas',
                                                'status' => 'Status',
                                                'is_active' => 'Aktif',
                                                'name' => 'Nama',
                                                'email' => 'Email',
                                                'phone' => 'No. HP',
                                                'address' => 'Alamat',
                                                'notes' => 'Catatan',
                                                'method' => 'Metode',
                                                'type' => 'Tipe',
                                                'amount' => 'Jumlah',
                                                'invoice_number' => 'Invoice',
                                                'return_due_date' => 'Jatuh tempo',
                                                'actual_return_date' => 'Tgl kembali',
                                                'rental_date' => 'Tgl sewa',
                                                'overdue_days' => 'Hari terlambat',
                                                'duration_days' => 'Durasi',
                                                'return_condition' => 'Kondisi',
                                                'damage_fee' => 'Biaya kerusakan',
                                                'is_returned' => 'Dikembalikan',
                                                default => Str::of($change['field'])->replace('_', ' ')->title()->__toString(),
                                            };
                                            $oldDisplay = is_numeric($change['old']) && $change['old'] >= 1000 ? 'Rp ' . number_format((float) $change['old'], 0, ',', '.') : ($change['old'] ?? '-');
                                            $newDisplay = is_numeric($change['new']) && $change['new'] >= 1000 ? 'Rp ' . number_format((float) $change['new'], 0, ',', '.') : ($change['new'] ?? '-');
                                        @endphp
                                        <span style="color:var(--text-soft)">
                                            <span style="color:#ef4444">{{ $oldDisplay }}</span>
                                            <span style="color:var(--text-soft)"> → </span>
                                            <span style="color:#10b981">{{ $newDisplay }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs" style="color:var(--text-soft)">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm align-middle">
                            <span class="text-xs" style="color:var(--text-soft)">{{ $log->model_type ? class_basename($log->model_type) : '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm align-middle text-center font-mono text-xs" style="color:var(--text-soft)">
                            {{ $log->model_id ?? '-' }}
                        </td>
                    </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-10 text-center bg-white">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-3">
                                <i data-lucide="scroll-text" class="w-5 h-5 text-slate-400"></i>
                            </div>
                            <p class="text-sm font-medium" style="color:var(--text-soft)">Tidak ada aktivitas ditemukan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="px-4 py-3 border-t border-gray-100 bg-white">
        {{ $logs->appends(request()->query())->links('components.pagination') }}
    </div>
    @endif
</div>
