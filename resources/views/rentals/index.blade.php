@extends('Layouts.app')

@section('title', 'Penyewaan — SewaJas')
@section('page-title', 'Penyewaan')
@section('subtitle', 'Daftar semua transaksi penyewaan')

@section('content')
    <!-- Quick Actions & Filters -->
    <div class="ds-card mb-6 p-3 sm:p-4 lg:p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('rentals.create') }}" class="btn-primary w-full sm:w-auto">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Tambah Penyewaan
                </a>
            </div>

            <form method="GET" class="flex flex-col sm:flex-row sm:items-center gap-3 w-full lg:w-auto">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari invoice atau pelanggan..." class="form-input w-full sm:w-64">

                <select name="status" class="form-input w-full sm:w-44">
                    <option value="">Semua Status</option>
                    <option value="waiting" {{ request('status') === 'waiting' ? 'selected' : '' }}>Menunggu</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Diproses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Terlambat</option>
                    <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Dikembalikan (belum lunas)</option>
                </select>

                <button type="submit" class="btn-filter w-full sm:w-auto whitespace-nowrap">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    Filter
                </button>

                @if(request('search') || request('status'))
                    <a href="{{ route('rentals.index') }}" class="btn-secondary w-full sm:w-auto whitespace-nowrap">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Reset
                    </a>
                @endif
            </form>
        </div>
    </div>

    <!-- Table Container -->
    <div class="ds-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <colgroup>
                    <col style="width: 12%">
                    <col style="width: 22%">
                    <col style="width: 8%">
                    <col style="width: 18%">
                    <col style="width: 12%">
                    <col style="width: 14%">
                    <col style="width: 14%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">No. Invoice</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Pelanggan</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Koleksi</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tanggal Sewa</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Status</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Total Bayar</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cream-sand/30">
                    @forelse($rentals as $rental)
                    <tr class="transition-colors">
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                            <div class="font-serif font-bold text-bark-dark text-sm leading-tight">{{ $rental->invoice_number }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                            <div class="font-semibold text-bark-dark text-sm leading-tight">{{ optional($rental->customer)->name ?? '-' }}</div>
                            <div class="text-xs text-stone-400 mt-0.5">{{ optional($rental->customer)->phone ?? '-' }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="flex justify-center">
                                <span class="badge badge-gray">
                                    {{ $rental->items->count() }} Item
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                            <div class="text-xs font-semibold text-bark-light leading-tight">
                                {{ optional($rental->rental_date)->format('d M Y') ?? '' }}
                            </div>
                            <div class="text-[10px] text-stone-400 mt-0.5">
                                Tempo: {{ optional($rental->return_due_date)->format('d M Y') ?? '' }}
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            @php
                                $statusClasses = [
                                    'waiting' => 'badge-gold',
                                    'processing' => 'badge-blue',
                                    'active' => 'badge-green',
                                    'overdue' => 'badge-red',
                                    'returned' => $rental->status_badge_color === 'green' ? 'badge-green' : ($rental->status_badge_color === 'red' ? 'badge-red' : 'badge-gold'),
                                    'cancelled' => 'badge-gray',
                                ];
                                $statusLabels = [
                                    'waiting' => 'Menunggu',
                                    'processing' => 'Diproses',
                                    'active' => 'Aktif',
                                    'overdue' => 'Terlambat',
                                    'returned' => $rental->status_label,
                                    'cancelled' => 'Dibatalkan',
                                ];
                            @endphp
                            <div class="flex flex-wrap justify-center gap-1">
                                <span class="badge {{ $statusClasses[$rental->rental_status] ?? 'badge-gray' }}">
                                    {{ $statusLabels[$rental->rental_status] ?? $rental->rental_status }}
                                </span>
                                @if((float) $rental->fine_amount > 0 && !($rental->rental_status === 'returned' && $rental->hasOutstandingFine()))
                                    @if($rental->fine_status === \App\Models\Rental::FINE_UNPAID)
                                        <span class="badge badge-red">Denda Belum Lunas</span>
                                    @elseif($rental->fine_status === \App\Models\Rental::FINE_PARTIAL)
                                        <span class="badge badge-gold">Denda Sebagian</span>
                                    @elseif($rental->fine_status === \App\Models\Rental::FINE_PAID)
                                        <span class="badge badge-green">Denda Lunas</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right">
                            <div class="font-serif font-bold text-bark-dark text-sm">Rp{{ number_format($rental->total_amount, 0, ',', '.') }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('rentals.show', $rental) }}" class="action-btn" title="View">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                @auth
                                @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminToko())
                                @php
                                    $isRentalLocked = in_array($rental->rental_status, ['active', 'overdue']) || $rental->payment_status === 'paid';
                                @endphp
                                          <a href="{{ $isRentalLocked ? '#' : route('rentals.edit', $rental) }}"
                                              class="action-btn {{ $isRentalLocked ? 'opacity-50 cursor-not-allowed' : '' }}"
                                   title="{{ $isRentalLocked ? 'Penyewaan sedang berjalan atau sudah dibayar' : 'Edit' }}"
                                   @if($isRentalLocked) onclick="event.preventDefault(); return false;" @endif>
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </a>
                                @endif
                                @endauth
                                <a href="{{ route('rentals.receipt.show', $rental) }}" class="action-btn" title="Receipt">
                                    <i data-lucide="receipt" class="w-4 h-4"></i>
                                </a>
                                <a href="{{ route('rentals.pdf', $rental) }}" class="action-btn" title="Download PDF">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                </a>

                                <a href="{{ route('rentals.whatsapp', $rental) }}" class="action-btn" title="Whatsapp">
                                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                                </a>

                                @auth
                                @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminToko())
                                @if(($rental->rental_status === 'waiting' && $rental->payment_status === 'unpaid') || in_array($rental->rental_status, ['cancelled', 'returned']))
                                <form method="POST" action="{{ route('rentals.destroy', $rental) }}" id="deleteRentalForm_{{ $rental->id }}" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDelete('deleteRentalForm_{{ $rental->id }}', 'penyewaan {{ $rental->invoice_number }}')" class="action-btn" title="Hapus Penyewaan">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                @endif
                                @endif
                                @endauth

                                @if($rental->rental_status == 'active' || $rental->rental_status == 'overdue')
                                    <a href="{{ route('rentals.show', $rental) }}" class="action-btn" title="Process Payment">
                                        <i data-lucide="wallet" class="w-4 h-4"></i>
                                    </a>
                                @endif

                                @if($rental->rental_status == 'returned')
                                    <a href="{{ route('rentals.show', $rental) }}" class="action-btn" title="Payment History">
                                        <i data-lucide="history" class="w-4 h-4"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center bg-cream/10">
                            <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                <div class="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center text-gold mb-4 shadow-sm">
                                    <i data-lucide="shirt" class="w-8 h-8"></i>
                                </div>
                                <h3 class="font-serif text-lg font-bold text-bark-dark mb-1">Belum Ada Transaksi</h3>
                                <p class="text-xs text-stone-400 mb-6 leading-relaxed">Mulai buat transaksi penyewaan jas pertama Anda dengan mudah.</p>
                                <a href="{{ route('rentals.create') }}" class="btn-primary">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Tambah Penyewaan
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rentals->hasPages())
        <div class="px-6 py-4 border-t border-cream-sand/50 bg-cream/5">
            {{ $rentals->appends(request()->query())->links('components.pagination') }}
        </div>
        @endif
</div>

@endsection
