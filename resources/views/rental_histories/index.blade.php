@extends('Layouts.app')

@section('title', 'Riwayat Penyewaan')
@section('page-title', 'Riwayat Penyewaan')

@section('content')
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Riwayat Penyewaan</h1>
                <p class="mt-1 text-sm text-slate-500">Daftar penyewaan yang sudah selesai (dikembalikan dan lunas).</p>
            </div>
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
            <form method="GET" class="grid grid-cols-2 xl:grid-cols-6 items-end gap-2.5 sm:gap-3">
                <div class="col-span-2 min-w-0 {{ auth()->user()->isSuperAdmin() ? 'xl:col-span-2' : 'xl:col-span-3' }}">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cari</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari invoice, nama pelanggan..."
                        class="w-full min-w-0 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </div>
                
                @if(auth()->user()->isSuperAdmin())
                <div class="col-span-2 min-w-0 xl:col-span-1">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cabang</label>
                    <select name="branch_id" class="w-full min-w-0 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">Semua Cabang</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="min-w-0">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="w-full min-w-0 rounded-2xl border border-slate-200 bg-white px-2.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </div>

                <div class="min-w-0">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="w-full min-w-0 rounded-2xl border border-slate-200 bg-white px-2.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </div>

                <button type="submit" class="col-span-2 w-full min-w-0 justify-center btn-primary xl:col-span-1">
                    <i data-lucide="search" class="h-4 w-4"></i>
                    Filter
                </button>
            </form>
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            @if($histories->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Invoice</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Pelanggan</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Cabang</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Tgl Sewa</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Tgl Kembali</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status Bayar</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dibuat Oleh</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($histories as $history)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3">
                                    <a href="{{ route('rental-histories.show', $history) }}" class="font-mono text-sm font-semibold text-blue-600 hover:underline">
                                        {{ $history->invoice_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-slate-900">{{ $history->customer->name ?? '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ $history->customer->phone ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $history->branch->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $history->rental_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $history->actual_return_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">Rp {{ number_format($history->total_amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $history->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($history->payment_status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                        {{ $history->payment_status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $history->createdBy->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('rental-histories.show', $history) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $histories->links() }}
                </div>
            @else
                <div class="p-12 text-center text-slate-500">
                    <i data-lucide="archive" class="mx-auto h-12 w-12 text-slate-300"></i>
                    <p class="mt-4 text-lg font-medium text-slate-700">Belum ada riwayat penyewaan</p>
                    <p class="mt-2 text-sm">Penyewaan yang sudah selesai dan lunas akan muncul di sini.</p>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
@endpush
