@extends('Layouts.app')
@section('title', 'Laporan Pengembalian')
@section('page-title', 'Laporan Pengembalian')
@section('subtitle', 'Monitoring pengembalian dan keterlambatan jas')

@section('content')
<div class="space-y-6">

    {{-- ── SUMMARY CARDS ───────────────────────────────────────── --}}
    {{-- controller: $summary['returned'], $summary['due_today'], $summary['overdue'] --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center" style="background:#10B98115">
                <i data-lucide="check-circle" class="w-6 h-6" style="color:#10B981"></i>
            </div>
            <div>
                <p class="text-xs" style="color:var(--text-soft)">Sudah Dikembalikan</p>
                <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $summary['returned'] }}</p>
                <p class="text-xs" style="color:var(--text-soft)">Periode ini</p>
            </div>
        </div>
        <div class="card p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center" style="background:#F59E0B15">
                <i data-lucide="clock" class="w-6 h-6" style="color:#F59E0B"></i>
            </div>
            <div>
                <p class="text-xs" style="color:var(--text-soft)">Jatuh Tempo Hari Ini</p>
                <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $summary['due_today'] }}</p>
                <p class="text-xs" style="color:var(--text-soft)">Harus segera dikembalikan</p>
            </div>
        </div>
        <div class="card p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center" style="background:#EF444415">
                <i data-lucide="alert-circle" class="w-6 h-6" style="color:#EF4444"></i>
            </div>
            <div>
                <p class="text-xs" style="color:var(--text-soft)">Terlambat Dikembalikan</p>
                <p class="text-2xl font-bold font-playfair text-red-500">{{ $summary['overdue'] }}</p>
                <p class="text-xs" style="color:var(--text-soft)">Melewati batas waktu</p>
            </div>
        </div>
    </div>

    {{-- ── TERLAMBAT (OVERDUE) ─────────────────────────────────── --}}
    {{-- controller: $overdue -- status active/rented, return_due_date < today --}}
    @if($overdue->count() > 0)
    <div class="ds-card overflow-hidden border-l-4" style="border-left-color:#EF4444">
        <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color:var(--border)">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>
            <h2 class="font-semibold text-sm text-red-600">Terlambat Dikembalikan ({{ $overdue->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 12%">
                <col style="width: 22%">
                <col style="width: 15%">
                <col style="width: 12%">
                <col style="width: 20%">
                <col style="width: 19%">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Invoice</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Pelanggan</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tgl Kembali</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Terlambat</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Total Denda</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-sand/30">
                @foreach($overdue as $t)
                <tr class="bg-red-50 transition-colors">
                    {{-- controller: invoice_number --}}
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-mono text-xs font-semibold" style="color:var(--primary)">{{ $t->invoice_number }}</td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                        {{-- controller: with(['customer']), relasi customer --}}
                        <p class="font-medium text-sm" style="color:var(--text-dark)">{{ $t->customer?->name ?? '-' }}</p>
                        <p class="text-xs" style="color:var(--text-soft)">{{ $t->customer?->phone ?? '-' }}</p>
                    </td>
                    {{-- controller: return_due_date --}}
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm font-semibold text-red-500">
                        {{ $t->return_due_date ? \Carbon\Carbon::parse($t->return_due_date)->format('d M Y') : '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <div class="flex justify-center">
                        <span class="badge badge-red">
                            {{ $t->return_due_date ? \Carbon\Carbon::parse($t->return_due_date)->diffForHumans() : '-' }}
                        </span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right text-sm font-semibold" style="color:var(--text-dark)">
                        {{ $t->fine_amount ? 'Rp ' . number_format($t->fine_amount, 0, ',', '.') : '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <a href="{{ route('rentals.show', $t) }}"
                           class="btn-primary text-xs py-1 px-3 inline-flex items-center gap-1">
                            <i data-lucide="eye" class="w-3 h-3"></i> Detail
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    {{-- ── JATUH TEMPO HARI INI ────────────────────────────────── --}}
    {{-- controller: $dueToday -- status active/rented, return_due_date = today --}}
    @if($dueToday->count() > 0)
    <div class="ds-card overflow-hidden border-l-4" style="border-left-color:#F59E0B">
        <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color:var(--border)">
            <i data-lucide="clock" class="w-4 h-4" style="color:#F59E0B"></i>
            <h2 class="font-semibold text-sm" style="color:#92400E">Jatuh Tempo Hari Ini ({{ $dueToday->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 15%">
                <col style="width: 25%">
                <col style="width: 25%">
                <col style="width: 35%">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Invoice</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Pelanggan</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">No. HP</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-sand/30">
                @foreach($dueToday as $t)
                <tr class="transition-colors" style="background:#FFFBEB">
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-mono text-xs font-semibold" style="color:var(--primary)">{{ $t->invoice_number }}</td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-medium text-sm" style="color:var(--text-dark)">{{ $t->customer?->name ?? '-' }}</td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                        {{-- customer->phone dari relasi --}}
                        @if($t->customer?->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $t->customer->phone) }}"
                           target="_blank"
                           class="flex items-center gap-1 text-sm text-green-600 hover:text-green-700">
                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                            {{ $t->customer->phone }}
                        </a>
                        @else
                        <span class="text-sm" style="color:var(--text-soft)">-</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <a href="{{ route('rentals.show', $t) }}"
                           class="btn-secondary text-xs py-1 px-3 inline-flex items-center gap-1">
                            <i data-lucide="eye" class="w-3 h-3"></i> Detail
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    {{-- ── FILTER PENGEMBALIAN ─────────────────────────────────── --}}
    <div class="ds-card p-5">
        <form method="GET" action="{{ route('reports.returns') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Periode</label>
                <select name="period" class="form-input" onchange="toggleReturnPeriod(this)">
                    <option value="">Pilih Tanggal</option>
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="week" {{ $period === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Dari Tanggal</label>
                <input type="date" name="date_from" id="return_date_from" value="{{ $dateFrom }}" class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Sampai Tanggal</label>
                <input type="date" name="date_to" id="return_date_to" value="{{ $dateTo }}" class="form-input">
            </div>
            <button type="submit" class="btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
            </button>
            <a href="{{ route('reports.returns') }}" class="btn-secondary">Reset</a>
        </form>
    </div>

    {{-- ── EXPORT BUTTONS ───────────────────────────────────────── --}}
    <div class="flex items-center justify-end gap-2 mb-6">
        <a href="{{ route('reports.export.returns.excel', request()->query()) }}" class="btn-secondary text-sm py-1.5 px-3">
            <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Export Excel
        </a>
        <a href="{{ route('reports.export.returns.pdf', request()->query()) }}" class="btn-secondary text-sm py-1.5 px-3">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Export PDF
        </a>
    </div>

    {{-- ── RIWAYAT PENGEMBALIAN ────────────────────────────────── --}}
    {{-- controller: $returned -- filter actual_return_date, status returned/completed --}}
    <div class="ds-card overflow-hidden">
        <div class="px-5 py-4 border-b" style="border-color:var(--border)">
            <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Riwayat Pengembalian</h2>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 12%">
                <col style="width: 20%">
                <col style="width: 14%">
                <col style="width: 18%">
                <col style="width: 17%">
                <col style="width: 12%">
                <col style="width: 7%">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Invoice</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Pelanggan</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tgl Sewa</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tgl Dikembalikan</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Total</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-sand/30">
                @forelse($returned as $t)
                <tr class="transition-colors">
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-mono text-xs font-semibold" style="color:var(--primary)">{{ $t->invoice_number }}</td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                        <p class="font-medium text-sm" style="color:var(--text-dark)">{{ $t->customer?->name ?? '-' }}</p>
                        <p class="text-xs" style="color:var(--text-soft)">{{ $t->customer?->phone ?? '-' }}</p>
                    </td>
                    {{-- controller: created_at sebagai tanggal sewa --}}
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">{{ $t->created_at->format('d M Y') }}</td>
                    {{-- controller: actual_return_date --}}
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">
                        {{ $t->actual_return_date ? \Carbon\Carbon::parse($t->actual_return_date)->format('d M Y') : '-' }}
                    </td>
                    {{-- controller: total_amount --}}
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right font-semibold text-sm" style="color:var(--text-dark)">
                        Rp {{ number_format($t->total_amount, 0, ',', '.') }}
                    </td>
                    {{-- controller: rental_status --}}
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <div class="flex justify-center">
                        <span class="badge {{ $t->rental_status === 'returned' && !$t->isCompleted() ? 'badge-red' : ($t->rental_status === 'completed' || $t->isCompleted() ? 'badge-green' : 'badge-blue') }}">
                            {{ match($t->rental_status) {
                                'pending'   => 'Menunggu',
                                'active'    => 'Aktif',
                                'rented'    => 'Disewa',
                                'returned'  => $t->status_label,
                                'completed' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                                default     => ucfirst($t->rental_status)
                            } }}
                        </span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <a href="{{ route('rentals.show', $t) }}"
                           class="p-1.5 rounded-lg hover:bg-gray-100 inline-flex" style="color:var(--text-soft)">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2" style="color:var(--border)"></i>
                        <p class="text-sm" style="color:var(--text-soft)">Belum ada data pengembalian</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($returned->hasPages())
        <div class="px-6 py-4 border-t" style="border-color:var(--border)">
            {{ $returned->links('components.pagination') }}
        </div>
        @endif
    </div>
</div>
@push('scripts')
<script>
lucide.createIcons();

function toggleReturnPeriod(select) {
    const dateFrom = document.getElementById('return_date_from');
    const dateTo = document.getElementById('return_date_to');
    if (!dateFrom || !dateTo) return;

    if (select.value === 'today' || select.value === 'week' || select.value === 'month') {
        dateFrom.disabled = true;
        dateTo.disabled = true;
    } else {
        dateFrom.disabled = false;
        dateTo.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const periodSelect = document.querySelector('select[name="period"]');
    if (periodSelect) {
        toggleReturnPeriod(periodSelect);
    }
});
</script>
@endpush
@endsection
