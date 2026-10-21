{{-- resources/views/reports/partials/transactions-content.blade.php --}}

{{-- ── FILTER ─────────────────────────────────────────────── --}}
<div class="ds-card p-5">
    <form method="GET" action="{{ route('reports.transactions') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Status</label>
            <select name="status" class="form-input">
                <option value="">Semua Status</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>
                        {{ match($s) {
                            'pending'   => 'Menunggu',
                            'active'    => 'Aktif',
                            'rented'    => 'Disewa',
                            'returned'  => 'Dikembalikan',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan',
                            default     => ucfirst($s)
                        } }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cari</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Invoice / nama / HP" class="form-input">
        </div>
        <button type="submit" class="btn-primary">
            <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
        </button>
        <a href="{{ route('reports.transactions') }}" class="btn-secondary">Reset</a>
    </form>
</div>

{{-- ── SUMMARY CARDS ───────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
    @foreach([
        ['label'=>'Total',      'value'=>$summary['total'],     'color'=>'var(--primary)', 'bg'=>'var(--secondary)', 'icon'=>'list'],
        ['label'=>'Pending',    'value'=>$summary['pending'],   'color'=>'#F59E0B',        'bg'=>'#F59E0B15',        'icon'=>'clock'],
        ['label'=>'Aktif',      'value'=>$summary['active'],    'color'=>'#3B82F6',        'bg'=>'#3B82F615',        'icon'=>'package'],
        ['label'=>'Selesai',    'value'=>$summary['completed'], 'color'=>'#10B981',        'bg'=>'#10B98115',        'icon'=>'check-circle'],
        ['label'=>'Dibatalkan', 'value'=>$summary['cancelled'], 'color'=>'#EF4444',        'bg'=>'#EF444415',        'icon'=>'x-circle'],
    ] as $card)
    <div class="card p-4 text-center">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center mx-auto mb-2"
             style="background:{{ $card['bg'] }}">
            <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4" style="color:{{ $card['color'] }}"></i>
        </div>
        <p class="text-xl font-bold font-playfair" style="color:var(--text-dark)">{{ $card['value'] }}</p>
        <p class="text-xs" style="color:var(--text-soft)">{{ $card['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- Total Nilai --}}
<div class="card p-4 flex items-center gap-3" style="background:var(--secondary)">
    <i data-lucide="coins" class="w-5 h-5" style="color:var(--primary)"></i>
    <p class="text-sm" style="color:var(--text-dark)">
        Total nilai transaksi selesai periode ini:
        <span class="font-bold font-playfair ml-1">Rp {{ number_format($summary['total_nilai'], 0, ',', '.') }}</span>
    </p>
</div>

{{-- ── TABEL ───────────────────────────────────────────────── --}}
<div class="ds-card overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border)">
        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Daftar Transaksi</h2>
        <span class="text-xs" style="color:var(--text-soft)">{{ $rentals->total() }} transaksi</span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <colgroup>
            <col style="width: 12%">
            <col style="width: 18%">
            <col style="width: 14%">
            <col style="width: 15%">
            <col style="width: 17%">
            <col style="width: 14%">
            <col style="width: 10%">
        </colgroup>
        <thead>
            <tr>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Invoice</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Pelanggan</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tgl Transaksi</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tgl Kembali</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Total</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Status</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cream-sand/30">
            @forelse($rentals as $t)
            <tr class="transition-colors">
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-mono text-xs font-semibold" style="color:var(--primary)">{{ $t->invoice_number }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                    <p class="font-medium text-sm" style="color:var(--text-dark)">{{ $t->customer?->name ?? '-' }}</p>
                    <p class="text-xs" style="color:var(--text-soft)">{{ $t->customer?->phone ?? '-' }}</p>
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">{{ $t->created_at->format('d M Y') }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm {{ $t->rental_status === 'active' && $t->return_due_date < now()->format('Y-m-d') ? 'text-red-500 font-semibold' : '' }}"
                    style="{{ $t->rental_status === 'active' && $t->return_due_date < now()->format('Y-m-d') ? '' : 'color:var(--text-soft)' }}">
                    {{ $t->return_due_date ? \Carbon\Carbon::parse($t->return_due_date)->format('d M Y') : '-' }}
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right font-semibold text-sm" style="color:var(--text-dark)">
                    Rp {{ number_format($t->total_amount, 0, ',', '.') }}
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                    <div class="flex justify-center">
                    <span class="badge {{ $t->rental_status === 'returned' && !$t->isCompleted() ? 'badge-red' : match($t->rental_status) {
                        'pending'   => 'badge-gold',
                        'active'    => 'badge-blue',
                        'rented'    => 'badge-blue',
                        'returned'  => 'badge-green',
                        'completed' => 'badge-green',
                        'cancelled' => 'badge-red',
                        default     => 'badge-gray'
                    } }}">{{ match($t->rental_status) {
                        'pending'   => 'Menunggu',
                        'active'    => 'Aktif',
                        'rented'    => 'Disewa',
                        'returned'  => $t->status_label,
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default     => ucfirst($t->rental_status)
                    } }}</span>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                    <div class="flex items-center justify-center">
                    <a href="{{ route('rentals.show', $t) }}"
                       class="p-1.5 rounded-lg hover:bg-gray-100 inline-flex" style="color:var(--text-soft)">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-5 py-12 text-center">
                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2" style="color:var(--border)"></i>
                    <p class="text-sm" style="color:var(--text-soft)">Tidak ada transaksi ditemukan</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($rentals->hasPages())
    <div class="px-6 py-4 border-t" style="border-color:var(--border)">
        {{ $rentals->appends(request()->query())->links('components.pagination') }}
    </div>
    @endif
</div>
