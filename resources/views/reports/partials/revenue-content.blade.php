{{-- resources/views/reports/partials/revenue-content.blade.php --}}

{{-- FILTER BAR --}}
@include('reports.partials.filter-bar')

{{-- STAT CARDS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    {{-- Total Pendapatan --}}
    <div class="stat-card">
        <div class="flex items-start justify-between relative z-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-soft)">
                    Total Pendapatan
                </p>
                <p class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">
                    Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                </p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: var(--gold-light)">
                <i data-lucide="trending-up" class="w-5 h-5" style="color: var(--gold)"></i>
            </div>
        </div>
    </div>

    {{-- Total Denda --}}
    <div class="stat-card">
        <div class="flex items-start justify-between relative z-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-soft)">
                    Total Denda Keterlambatan
                </p>
                <p class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">
                    Rp {{ number_format($totalLateFee ?? 0, 0, ',', '.') }}
                </p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #FFF1F0">
                <i data-lucide="alert-triangle" class="w-5 h-5" style="color: #C0392B"></i>
            </div>
        </div>
    </div>

    {{-- Total Hari Transaksi --}}
    <div class="stat-card">
        <div class="flex items-start justify-between relative z-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-soft)">
                    Total Hari Transaksi
                </p>
                <p class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">
                    {{ $revenueData->count() }}
                    <span class="text-base font-normal" style="color: var(--text-soft)">hari</span>
                </p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #EFF6FF">
                <i data-lucide="calendar-days" class="w-5 h-5" style="color: #1D4ED8"></i>
            </div>
        </div>
    </div>

</div>

{{-- TABEL DATA --}}
<div class="ds-card overflow-hidden">
    <div class="px-5 py-4 flex items-center justify-between"
         style="border-bottom: 1px solid var(--border)">
        <h2 class="font-semibold text-sm" style="color: var(--text-dark)">
            Rincian Pendapatan per Hari
        </h2>
        <span class="text-xs" style="color: var(--text-soft)">
            {{ $revenueData->count() }} entri
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <colgroup>
                <col style="width: 35%">
                <col style="width: 20%">
                <col style="width: 25%">
                <col style="width: 20%">
            </colgroup>
            <thead>
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tanggal</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Total Rental</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Denda</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Pendapatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-sand/30">
                @forelse ($revenueData as $row)
                    <tr class="transition-colors">
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle" style="color: var(--text-dark)">
                            {{ \Carbon\Carbon::parse($row->date)->isoFormat('dddd, D MMMM Y') }}
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center" style="color: var(--text-soft)">
                            {{ number_format($row->total_rentals) }}
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right">
                            @if ($row->total_late_fee > 0)
                                <span style="color: #C0392B" class="font-medium">
                                    Rp {{ number_format($row->total_late_fee, 0, ',', '.') }}
                                </span>
                            @else
                                <span style="color: var(--text-soft)">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right font-semibold" style="color: #15803D">
                            Rp {{ number_format($row->total_revenue, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <i data-lucide="inbox" class="w-8 h-8 opacity-30"
                                   style="color: var(--text-soft)"></i>
                                <p class="text-sm" style="color: var(--text-soft)">
                                    Tidak ada data untuk periode ini
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if ($revenueData->isNotEmpty())
                <tfoot>
                    <tr>
                        <td class="px-5 py-3.5 font-bold text-sm" style="color: var(--text-dark); background: linear-gradient(135deg, #F8F5F0, #EDE7DE);">
                            Total Keseluruhan
                        </td>
                        <td class="px-5 py-3.5 text-center font-bold text-sm" style="color: var(--text-dark); background: linear-gradient(135deg, #F8F5F0, #EDE7DE);">
                            {{ number_format($revenueData->sum('total_rentals')) }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm" style="color: #C0392B; background: linear-gradient(135deg, #F8F5F0, #EDE7DE);">
                            Rp {{ number_format($totalLateFee ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm" style="color: #15803D; background: linear-gradient(135deg, #F8F5F0, #EDE7DE);">
                            Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
