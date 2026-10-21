@extends('Layouts.app')

@section('title', 'Pelanggan — RentalJas')
@section('page-title', 'Pelanggan')
@section('subtitle', 'Kelola semua data pelanggan')

@section('content')
    <!-- Deactivated Count Alert (Super Admin only) — placed OUTSIDE cards for prominence -->
    @auth
    @if(auth()->user()->isSuperAdmin() && isset($deactivatedCount) && $deactivatedCount > 0)
    <div class="ds-card mb-6 px-5 py-4 bg-amber-50/80 border border-amber-200 flex items-center gap-3 ds-slide-up">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100">
            <i data-lucide="info" class="w-5 h-5 text-amber-600"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-amber-900">
                <strong>{{ $deactivatedCount }}</strong> pelanggan dalam status dinonaktifkan.
            </p>
        </div>
        <a href="{{ route('customers.index', ['status' => 'deactivated']) }}" class="btn-secondary whitespace-nowrap text-amber-700 border-amber-300 hover:bg-amber-100">
            <i data-lucide="eye" class="w-4 h-4"></i>
            Lihat
        </a>
    </div>
    @endif
    @endauth

    <!-- Quick Actions & Filters -->
    <div class="ds-card p-4 sm:p-6 mb-6">
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
                @auth
                @if(in_array(auth()->user()->role, ['super_admin','admin_toko','sales']))
                <a href="{{ route('customers.create') }}" class="btn-primary flex w-full items-center justify-center whitespace-nowrap sm:w-auto">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Tambah Pelanggan
                </a>
                @endif
                @endauth

                @auth
                @if(in_array(auth()->user()->role, ['super_admin','admin_toko']))
                <a href="{{ route('customers.export') }}" class="btn-secondary flex w-full items-center justify-center whitespace-nowrap sm:w-auto">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Ekspor Excel
                </a>
                @endif
                @endauth
            </div>

            <div class="grid w-full grid-cols-2 items-center gap-3 lg:flex lg:flex-nowrap" x-data="{}">
                <div class="relative col-span-2 min-w-0 lg:min-w-[320px]" style="flex: 3 1 0%;">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                    <input type="text" placeholder="Cari nama atau nomor HP..."
                           class="form-input ds-input-icon h-11 w-full min-w-0"
                           id="search-customer"
                           value="{{ request('search') }}"
                           onkeyup="if(event.key === 'Enter') applyCustomerFilters()">
                </div>

                @auth
                @if(auth()->user()->isSuperAdmin())
                <select class="form-input col-span-1 h-11 w-full min-w-0" style="flex: 0 0 12rem;" id="status-filter" onchange="applyCustomerFilters()">
                    <option value="active" {{ ($statusFilter ?? request('status', 'active')) === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="deactivated" {{ ($statusFilter ?? request('status')) === 'deactivated' ? 'selected' : '' }}>Dinonaktifkan</option>
                    <option value="all" {{ ($statusFilter ?? request('status')) === 'all' ? 'selected' : '' }}>Semua</option>
                </select>
                @endif
                @endauth

                @auth
                @if(auth()->user()->isSuperAdmin())
                <select class="form-input col-span-1 h-11 w-full min-w-0" style="flex: 0 0 12rem;" id="bl-filter" onchange="applyCustomerFilters()">
                @else
                <select class="form-input col-span-1 h-11 w-full min-w-0" style="flex: 0 0 12rem;" id="bl-filter" onchange="applyCustomerFilters()">
                @endif
                @endauth
                    <option value="">Daftar Blokir: Semua</option>
                    <option value="normal" {{ request('bl_status') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="blacklisted" {{ request('bl_status') === 'blacklisted' ? 'selected' : '' }}>Daftar Blokir</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="ds-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <colgroup>
                        <col style="width: 22%">
                        <col style="width: 18%">
                        <col style="width: 10%">
                        <col style="width: 12%">
                        <col style="width: 22%">
                        <col style="width: 16%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Nama</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Nomor Handphone</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Status</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Jumlah Transaksi</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Tanggal Bergabung</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cream-sand/30">
                        @forelse($customers as $customer)
                        @php
                            $isDeactivated = $customer->trashed();
                        @endphp
                        <tr class="transition-colors {{ $isDeactivated ? 'opacity-70 bg-slate-50' : '' }}">
                            <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shadow-sm flex-shrink-0 {{ $isDeactivated ? 'grayscale' : '' }}"
                                         style="background-image: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: #fff; outline: 3px solid #fff; outline-offset: 0; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.22), 0 3px 8px rgba(99, 102, 241, 0.12);">
                                        {{ strtoupper(substr($customer->name, 0, 2)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-bark-dark truncate text-sm">{{ $customer->name }}</div>
                                        @if($isDeactivated)
                                        <span class="text-xs text-amber-600 font-medium">Dinonaktifkan</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                                <div class="font-semibold text-bark-light">{{ $customer->phone }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                                <div class="flex justify-center">
                                @if($isDeactivated)
                                <span class="badge badge-gold">Dinonaktifkan</span>
                                @elseif($customer->is_blacklisted)
                                <span class="badge badge-red">Blacklist</span>
                                @else
                                <span class="badge badge-green">Aktif</span>
                                @endif
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                                <div class="font-bold text-bark-dark">{{ $customer->rentals_count ?? 0 }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-left">
                                <div class="text-bark-light">{{ $customer->created_at ? $customer->created_at->format('d M Y') : '-' }}</div>
                                @if($isDeactivated && $customer->deleted_at)
                                <div class="text-xs text-amber-500 mt-1">Dinonaktifkan: {{ $customer->deleted_at->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if($isDeactivated)
                                        @auth
                                    @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminToko())
                                        <form method="POST" action="{{ route('customers.restore', $customer->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="p-2 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-xl transition-all" title="Pulihkan" aria-label="Pulihkan {{ $customer->name }}">
                                                <i data-lucide="rotate-ccw" class="w-4.5 h-4.5"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if(auth()->user()->isSuperAdmin())
                                        <form method="POST" action="{{ route('customers.force-destroy', $customer) }}" id="deleteCustomer_{{ $customer->id }}" class="hidden">
                                            @csrf @method('DELETE')
                                        </form>
                                        <button type="button" onclick="confirmDelete('deleteCustomer_{{ $customer->id }}', 'customer {{ $customer->name }}')"
                                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"
                                                title="Hapus Permanen"
                                                aria-label="Hapus Permanen {{ $customer->name }}">
                                            <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                        </button>
                                    @endif
                                        @endauth
                                    @else
                                    <a href="{{ route('customers.show', $customer) }}" class="p-2 text-stone-400 hover:text-bark-dark hover:bg-cream/40 rounded-xl transition-all" title="Detail" aria-label="Detail {{ $customer->name }}">
                                        <i data-lucide="eye" class="w-4.5 h-4.5"></i>
                                    </a>
                                    @can('update', $customer)
                                    <a href="{{ route('customers.edit', $customer) }}" class="p-2 text-stone-400 hover:text-gold hover:bg-gold/10 rounded-xl transition-all" title="Edit" aria-label="Edit {{ $customer->name }}">
                                        <i data-lucide="edit-3" class="w-4.5 h-4.5"></i>
                                    </a>
                                    @endcan
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', (str_starts_with($customer->phone, '0') ? '62'.substr($customer->phone,1) : $customer->phone)) }}" target="_blank" class="p-2 text-stone-400 hover:text-green-600 hover:bg-green-50 rounded-xl transition-all" title="WhatsApp" aria-label="WhatsApp {{ $customer->name }}">
                                        <i data-lucide="message-square" class="w-4.5 h-4.5"></i>
                                    </a>
                                    @auth
                                    @can('delete', $customer)
                                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" id="deleteCustomer_{{ $customer->id }}" class="hidden">
                                        @csrf @method('DELETE')
                                    </form>
                                    <button type="button" onclick="confirmDelete('deleteCustomer_{{ $customer->id }}', 'customer {{ $customer->name }}')"
                                            class="p-2 text-stone-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"
                                            title="Hapus"
                                            aria-label="Hapus {{ $customer->name }}">
                                        <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                    </button>
                                    @endcan
                                    @endauth
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center bg-cream/10">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center text-gold mb-4 shadow-sm">
                                        <i data-lucide="users" class="w-8 h-8"></i>
                                    </div>
                                    @if(request('status') === 'deactivated')
                                    <h3 class="font-serif text-lg font-bold text-bark-dark mb-1">Tidak Ada Pelanggan Dinonaktifkan</h3>
                                    <p class="text-xs text-stone-400 mb-6 leading-relaxed">Semua pelanggan dalam status aktif.</p>
                                    <a href="{{ route('customers.index') }}" class="btn-secondary">
                                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                        Lihat Pelanggan Aktif
                                    </a>
                                    @else
                                    <h3 class="font-serif text-lg font-bold text-bark-dark mb-1">Belum Ada Pelanggan</h3>
                                    <p class="text-xs text-stone-400 mb-6 leading-relaxed">Mulai daftarkan data pelanggan pertama Anda untuk memulai manajemen penyewaan jas.</p>
                                    <a href="{{ route('customers.create') }}" class="btn-primary">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                                        Tambah Pelanggan
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
            <div class="px-6 py-4 border-t border-cream-sand/50 bg-cream/5">
                {{ $customers->appends(request()->query())->links('components.pagination') }}
            </div>
            @endif
        </div>
@endsection

@push('scripts')
<script>
function applyCustomerFilters() {
    const search = document.getElementById('search-customer')?.value || '';
    const status = document.getElementById('status-filter')?.value || 'active';
    const bl = document.getElementById('bl-filter')?.value || '';
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    params.set('status', status);
    if (bl) params.set('bl_status', bl);
    window.location.href = '?' + params.toString();
}

</script>
@endpush
