{{-- resources/views/reports/payments.blade.php --}}
@extends('Layouts.app')
@section('title', 'Laporan Keuangan')
@section('page-title', 'Laporan Keuangan')
@section('subtitle', 'Ringkasan pemasukan, denda, dan rincian pembayaran')

@section('content')
<div class="space-y-6">

    {{-- ── SUMMARY CARDS ───────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach([
            ['label'=>'Pemasukan Bersih', 'value'=>'Rp '.number_format($summary['total_amount'], 0, ',', '.'), 'description'=>'Termasuk pengembalian dana dan pembatalan pembayaran', 'color'=>'#15803D', 'bg'=>'#F0FDF4', 'icon'=>'wallet'],
            ['label'=>'Pembayaran Sewa', 'value'=>'Rp '.number_format($summary['rental_received'], 0, ',', '.'), 'description'=>'Pembayaran sewa yang tercatat pada periode', 'color'=>'#3B82F6', 'bg'=>'#EFF6FF', 'icon'=>'receipt'],
            ['label'=>'Denda Diterima', 'value'=>'Rp '.number_format($summary['fine_received'], 0, ',', '.'), 'description'=>'Pembayaran denda yang tercatat pada periode', 'color'=>'#B7791F', 'bg'=>'#FFFBEB', 'icon'=>'badge-check'],
            ['label'=>'Denda Belum Lunas', 'value'=>'Rp '.number_format($summary['outstanding_fines'], 0, ',', '.'), 'description'=>'Saldo denda saat ini, di luar filter periode', 'color'=>'#DC2626', 'bg'=>'#FEF2F2', 'icon'=>'triangle-alert'],
        ] as $card)
        <div class="card p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-soft)">{{ $card['label'] }}</p>
                    <p class="mt-2 text-base font-bold leading-tight font-playfair sm:text-xl [overflow-wrap:anywhere]" style="color:{{ $card['color'] }}">{{ $card['value'] }}</p>
                    <p class="mt-1 hidden text-xs leading-relaxed sm:block" style="color:var(--text-soft)">{{ $card['description'] }}</p>
                </div>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:{{ $card['bg'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="w-5 h-5" style="color:{{ $card['color'] }}"></i>
                </span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── FILTER ─────────────────────────────────────────────── --}}
    <div class="ds-card p-3 sm:p-4">
        <form method="GET" action="{{ route('reports.index') }}" class="grid grid-cols-2 items-end gap-2 sm:gap-3 xl:w-fit xl:max-w-full xl:grid-cols-[9rem_8rem_9rem_9rem_16rem_auto]">
            @if($isSuperAdmin || auth()->user()->isAdminToko())
            <div class="min-w-0">
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cabang</label>
                <select name="branch_id" class="form-input h-11 w-full min-w-0">
                    @if($isSuperAdmin)
                    <option value="">Semua Cabang</option>
                    @endif
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="min-w-0">
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Periode</label>
                <select name="period" class="form-input h-11 w-full min-w-0" onchange="togglePaymentPeriod(this)">
                    <option value="" {{ empty($period) ? 'selected' : '' }}>Rentang Tanggal</option>
                    <option value="today" {{ ($period ?? '') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="week" {{ ($period ?? '') === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="month" {{ ($period ?? '') === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                </select>
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Dari Tanggal</label>
                <input type="date" name="date_from" id="payment_date_from" value="{{ $dateFrom ?? '' }}" class="form-input h-11 w-full min-w-0">
            </div>
            <div class="min-w-0">
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Sampai Tanggal</label>
                <input type="date" name="date_to" id="payment_date_to" value="{{ $dateTo ?? '' }}" class="form-input h-11 w-full min-w-0">
            </div>
            @if($isSuperAdmin || auth()->user()->isAdminToko())
            <div class="col-span-2 min-w-0 xl:col-span-1">
            @else
            <div class="col-span-2 min-w-0 xl:col-span-1">
            @endif
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cari</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Invoice / nama / HP" class="form-input h-11 w-full min-w-0">
            </div>
            <div class="col-span-2 flex h-11 min-w-0 gap-2 xl:col-span-1">
                <button type="submit" class="btn-primary flex min-w-0 flex-1 items-center justify-center gap-1.5 px-2">
                    <i data-lucide="search" class="w-4 h-4 shrink-0"></i>
                    <span>Tampilkan</span>
                </button>
                <a href="{{ route('reports.index') }}" class="btn-secondary flex min-w-0 flex-1 items-center justify-center px-2">Reset</a>
            </div>
        </form>
    </div>

    {{-- ── EXPORT BUTTONS ───────────────────────────────────────── --}}
    <div class="flex items-center justify-end gap-2 mb-6">
        <a href="{{ route('reports.export.payments.excel', array_merge(request()->query(), ['period' => $period])) }}" class="btn-secondary text-sm py-1.5 px-3">
            <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Export Excel
        </a>
        <a href="{{ route('reports.export.payments.pdf', array_merge(request()->query(), ['period' => $period])) }}" class="btn-secondary text-sm py-1.5 px-3">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Export PDF
        </a>
    </div>

    {{-- ── TABEL PEMBAYARAN ───────────────────────────────────────── --}}
    <div class="ds-card overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border)">
            <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Rincian Arus Kas</h2>
            <span class="text-xs" style="color:var(--text-soft)">{{ $payments->total() }} catatan pembayaran</span>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 12%">
                <col style="width: 12%">
                <col style="width: 12%">
                <col style="width: 16%">
                <col style="width: 14%">
                <col style="width: 10%">
                <col style="width: 10%">
                <col style="width: 14%">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tanggal</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Invoice</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Pelanggan</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Jumlah</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Metode</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Tipe</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Catatan</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Diterima Oleh</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-sand/30">
                @forelse($payments as $p)
                <tr class="transition-colors">
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">
                        {{ $p->paid_at?->format('d M Y H:i') ?? '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-mono text-xs font-semibold" style="color:var(--primary)">
                        {{ $p->rental?->invoice_number ?? '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                        <p class="font-medium text-sm" style="color:var(--text-dark)">{{ $p->rental?->customer?->name ?? '-' }}</p>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right font-semibold text-sm" style="color:#15803D">
                        Rp {{ number_format((float) $p->amount, 0, ',', '.') }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <span class="badge badge-gray">{{ $p->method_label ?? $p->method }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        @php
                            $typeLabel = match($p->type) {
                                'rental' => 'Sewa',
                                'deposit' => 'Deposit',
                                'late_fee', 'damage_fee' => 'Denda',
                                'refund' => 'Refund',
                                default => ucfirst($p->type)
                            };
                            $typeClass = match($p->type) {
                                'rental', 'deposit' => 'badge-green',
                                'late_fee', 'damage_fee' => 'badge-gold',
                                'refund' => 'badge-red',
                                default => 'badge-gray'
                            };
                        @endphp
                        <span class="badge {{ $typeClass }}">{{ $typeLabel }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">
                        {{ $p->notes ?? '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">
                        {{ $p->receivedBy?->name ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-12 text-center">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2" style="color:var(--border)"></i>
                        <p class="text-sm" style="color:var(--text-soft)">Tidak ada pembayaran ditemukan</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($payments->hasPages())
        <div class="px-6 py-4 border-t" style="border-color:var(--border)">
            {{ $payments->appends(request()->query())->links('components.pagination') }}
        </div>
        @endif
    </div>
</div>
@push('scripts')
<script>
lucide.createIcons();

function togglePaymentPeriod(select) {
    const dateFrom = document.getElementById('payment_date_from');
    const dateTo = document.getElementById('payment_date_to');
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
        togglePaymentPeriod(periodSelect);
    }
});
</script>
@endpush
@endsection
