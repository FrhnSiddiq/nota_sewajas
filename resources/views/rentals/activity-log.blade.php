@extends('Layouts.app')
@section('title', 'Log Aktivitas Penyewaan')
@section('page-title', 'Log Aktivitas Penyewaan')
@section('subtitle', 'Riwayat semua aktivitas transaksi penyewaan')

@section('content')
<div class="space-y-6">

    {{-- FILTER --}}
    <div class="ds-card p-5">
        <form method="GET" action="{{ route('rentals.activity-log') }}" class="flex flex-wrap items-end gap-3">
            @if($isSuperAdmin)
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cabang</label>
                <select name="branch_id" class="form-input">
                    <option value="">Semua Cabang</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Sampai Tanggal</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cari</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice / nama user / deskripsi" class="form-input">
            </div>
            <button type="submit" class="btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
            </button>
            <a href="{{ route('rentals.activity-log') }}" class="btn-secondary">Reset</a>
        </form>
    </div>

    {{-- EXPORT BUTTONS --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('rentals.activity-log.export.excel', request()->query()) }}" class="btn-primary">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Export Excel
        </a>
        <a href="{{ route('rentals.activity-log.export.pdf', request()->query()) }}" class="btn-secondary">
            <i data-lucide="file-text" class="w-4 h-4"></i> Export PDF
        </a>
    </div>

    {{-- TABLE --}}
    <div class="ds-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-4 py-3 font-semibold text-slate-600">Waktu</th>
                        <th class="px-4 py-3 font-semibold text-slate-600">Cabang</th>
                        <th class="px-4 py-3 font-semibold text-slate-600">User</th>
                        <th class="px-4 py-3 font-semibold text-slate-600">Aksi</th>
                        <th class="px-4 py-3 font-semibold text-slate-600">Deskripsi</th>
                        <th class="px-4 py-3 font-semibold text-slate-600">Invoice</th>
                        <th class="px-4 py-3 font-semibold text-slate-600">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        @php
                            $rental = \App\Models\Rental::find($log->model_id);
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->rental?->branch?->name ?? $log->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->user?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->description }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $rental?->invoice_number ?? ($log->model_id ?? '-') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada log aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
