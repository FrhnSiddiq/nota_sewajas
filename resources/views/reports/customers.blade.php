{{-- resources/views/reports/customers.blade.php --}}
@extends('Layouts.app')
@section('title', 'Laporan Customer')
@section('page-title', 'Laporan Customer')
@section('subtitle', 'Data customer dan riwayat transaksi')

@section('content')
<div class="space-y-6">

    {{-- ── SUMMARY CARDS ───────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach([
            ['label'=>'Total Customer', 'value'=>$customers->total(), 'color'=>'var(--primary)', 'bg'=>'var(--secondary)', 'icon'=>'users'],
            ['label'=>'Dengan Transaksi', 'value'=>$customers->where('total_rentals', '>', 0)->count(), 'color'=>'#3B82F6', 'bg'=>'#3B82F615', 'icon'=>'shopping-bag'],
            ['label'=>'Blm Pernah Transaksi', 'value'=>$customers->where('total_rentals', 0)->count(), 'color'=>'#F59E0B', 'bg'=>'#F59E0B15', 'icon'=>'user-x'],
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

    {{-- ── FILTER ─────────────────────────────────────────────── --}}
    <div class="ds-card p-5">
        <form method="GET" action="{{ route('reports.customers') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cari Customer</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Nama / HP / email..." class="form-input">
            </div>
            <button type="submit" class="btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
            </button>
            <a href="{{ route('reports.customers') }}" class="btn-secondary">Reset</a>
        </form>
    </div>

    {{-- ── EXPORT BUTTONS ───────────────────────────────────────── --}}
    <div class="flex items-center justify-end gap-2 mb-6">
        <a href="{{ route('reports.export.customers.excel', request()->query()) }}" class="btn-secondary text-sm py-1.5 px-3">
            <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Export Excel
        </a>
        <a href="{{ route('reports.export.customers.pdf', request()->query()) }}" class="btn-secondary text-sm py-1.5 px-3">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Export PDF
        </a>
    </div>

    {{-- ── TABEL CUSTOMER ───────────────────────────────────────── --}}
    <div class="ds-card overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border)">
            <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Daftar Customer</h2>
            <span class="text-xs" style="color:var(--text-soft)">{{ $customers->total() }} customer</span>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 22%">
                <col style="width: 14%">
                <col style="width: 20%">
                <col style="width: 10%">
                <col style="width: 12%">
                <col style="width: 12%">
                <col style="width: 10%">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Nama</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">No. HP</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Email</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Total Transaksi</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Total Nilai</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Transaksi Terakhir</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-sand/30">
                @forelse($customers as $c)
                <tr class="transition-colors">
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                        <p class="font-medium text-sm" style="color:var(--text-dark)">{{ $c->name }}</p>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">{{ $c->phone ?? '-' }}</td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">{{ $c->email ?? '-' }}</td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        <span class="font-bold font-playfair" style="color:var(--text-dark)">{{ $c->total_rentals }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right font-semibold text-sm" style="color:#15803D">
                        {{ $c->total_transaction_value ? 'Rp ' . number_format((float) $c->total_transaction_value, 0, ',', '.') : 'Rp 0' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">
                        {{ $c->last_rental_date ? \Carbon\Carbon::parse($c->last_rental_date)->format('d M Y') : '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                        @if($c->is_blacklisted)
                            <span class="badge badge-red">Blacklist</span>
                        @else
                            <span class="badge badge-green">Aktif</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2" style="color:var(--border)"></i>
                        <p class="text-sm" style="color:var(--text-soft)">Tidak ada customer ditemukan</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($customers->hasPages())
        <div class="px-6 py-4 border-t" style="border-color:var(--border)">
            {{ $customers->appends(request()->query())->links('components.pagination') }}
        </div>
        @endif
    </div>
</div>
@push('scripts')
<script>lucide.createIcons();</script>
@endpush
@endsection
