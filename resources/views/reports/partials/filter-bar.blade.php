{{--
    resources/views/reports/partials/filter-bar.blade.php

    Variabel dari controller (via sharedViewData()):
      $isSuperAdmin     : bool
      $branches         : Collection<Branch>  (kosong jika bukan super_admin)
      $selectedBranchId : int|null

    Opsional:
      $excelRoute  : nama route untuk export excel (default: reports.export.excel)
      $pdfRoute    : nama route untuk export pdf   (default: reports.export.pdf)
--}}

<div class="card mb-6 overflow-hidden">
    <form method="GET" action="{{ url()->current() }}">

        {{-- ── BODY FILTER ── --}}
        <div class="flex flex-wrap items-end gap-4 p-5">

            {{-- DROPDOWN BRANCH: super_admin & admin_toko --}}
            @if ($isSuperAdmin || auth()->user()->isAdminToko())
                <div class="flex flex-col gap-1.5 min-w-[200px]">
                    <label class="text-xs font-semibold uppercase tracking-wider"
                           style="color: var(--text-soft)">
                        Cabang
                    </label>
                    <div class="relative">
                        <select name="branch_id" class="form-input pr-8 appearance-none cursor-pointer">
                            @if ($isSuperAdmin)
                                <option value="">— Semua Cabang —</option>
                            @endif
                            @foreach ($branches as $branch)
                                <option
                                    value="{{ $branch->id }}"
                                    {{ (int) $selectedBranchId === $branch->id ? 'selected' : '' }}
                                >
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down"
                           class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none"
                           style="color: var(--text-soft)"></i>
                    </div>
                </div>
            @else
                {{-- sales: tampilkan cabang sendiri, read-only --}}
                <div class="flex flex-col gap-1.5 min-w-[200px]">
                    <label class="text-xs font-semibold uppercase tracking-wider"
                           style="color: var(--text-soft)">
                        Cabang
                    </label>
                    <div class="form-input flex items-center gap-2 opacity-60 cursor-not-allowed"
                         style="background: var(--secondary)">
                        <i data-lucide="building-2" class="w-3.5 h-3.5 flex-shrink-0"
                           style="color: var(--text-soft)"></i>
                        <span class="text-sm truncate" style="color: var(--text-dark)">
                            {{ auth()->user()->branch?->name ?? '-' }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- PERIODE CEPAT --}}
            <div class="flex flex-col gap-1.5">
                <label for="period"
                       class="text-xs font-semibold uppercase tracking-wider"
                       style="color: var(--text-soft)">
                    Periode
                </label>
                <select name="period" id="period" class="form-input" onchange="toggleReportPeriod(this)">
                    <option value="">Pilih Tanggal</option>
                    <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="week" {{ request('period') === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                </select>
            </div>

            {{-- DARI TANGGAL --}}
            <div class="flex flex-col gap-1.5">
                <label for="start_date"
                       class="text-xs font-semibold uppercase tracking-wider"
                       style="color: var(--text-soft)">
                    Dari
                </label>
                <input
                    type="date"
                    name="start_date"
                    id="start_date"
                    class="form-input"
                    value="{{ request('start_date') }}"
                >
            </div>

            {{-- SAMPAI TANGGAL --}}
            <div class="flex flex-col gap-1.5">
                <label for="end_date"
                       class="text-xs font-semibold uppercase tracking-wider"
                       style="color: var(--text-soft)">
                    Sampai
                </label>
                <input
                    type="date"
                    name="end_date"
                    id="end_date"
                    class="form-input"
                    value="{{ request('end_date') }}"
                >
            </div>

            {{-- TOMBOL TAMPILKAN --}}
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider opacity-0">—</label>
                <button type="submit" class="btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Tampilkan
                </button>
            </div>

            {{-- RESET --}}
            @if (request()->hasAny(['start_date', 'end_date', 'branch_id', 'period']))
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider opacity-0">—</label>
                    <a href="{{ url()->current() }}" class="btn-secondary">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Reset
                    </a>
                </div>
            @endif

        </div>

        {{-- ── FOOTER: TOMBOL EXPORT ── --}}
        <div class="flex items-center justify-between px-5 py-3"
             style="background: var(--secondary); border-top: 1px solid var(--border)">

            {{-- Keterangan scope --}}
            <div class="flex items-center gap-2 text-xs" style="color: var(--text-soft)">
                @if ($isSuperAdmin || auth()->user()->isAdminToko())
                    @if ($selectedBranchId)
                        <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                        Ekspor untuk cabang
                        <span class="font-semibold" style="color: var(--text-dark)">
                            {{ $branches->firstWhere('id', $selectedBranchId)?->name }}
                        </span>
                    @else
                        <i data-lucide="globe" class="w-3.5 h-3.5" style="color: var(--gold)"></i>
                        Ekspor akan mencakup
                        <span class="font-semibold" style="color: var(--text-dark)">semua cabang</span>
                    @endif
                @else
                    <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                    Ekspor hanya untuk cabang Anda
                @endif
            </div>

            {{-- Tombol export --}}
            <div class="flex items-center gap-2">
                {{-- Excel --}}
                <a href="{{ route($excelRoute ?? 'reports.export.excel', request()->query()) }}"
                   class="btn-secondary text-sm py-1.5 px-3"
                   style="color: #15803D; border-color: #BBF7D0; background: #F0FDF4">
                     <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i>
                     Excel
                      @if (($isSuperAdmin || auth()->user()->isAdminToko()) && ! $selectedBranchId)
                          <span class="text-xs px-1.5 py-0.5 rounded-full font-semibold"
                                style="background: var(--gold-light); color: #92681A">
                              Semua
                          </span>
                      @endif
                 </a>

                 {{-- PDF --}}
                 <a href="{{ route($pdfRoute ?? 'reports.export.pdf', request()->query()) }}"
                    class="btn-secondary text-sm py-1.5 px-3"
                    style="color: #C0392B; border-color: #FECACA; background: #FFF1F0">
                     <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                     PDF
                      @if (($isSuperAdmin || auth()->user()->isAdminToko()) && ! $selectedBranchId)
                          <span class="text-xs px-1.5 py-0.5 rounded-full font-semibold"
                                style="background: var(--gold-light); color: #92681A">
                              Semua
                          </span>
                      @endif
                 </a>
            </div>
        </div>

    </form>
</div>

@push('scripts')
<script>
function toggleReportPeriod(select) {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    if (!startDate || !endDate) return;

    if (select.value === 'today' || select.value === 'week' || select.value === 'month') {
        startDate.disabled = true;
        endDate.disabled = true;
    } else {
        startDate.disabled = false;
        endDate.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const periodSelect = document.getElementById('period');
    if (periodSelect) {
        toggleReportPeriod(periodSelect);
    }
});
</script>
@endpush
