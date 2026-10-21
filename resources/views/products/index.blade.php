@extends('Layouts.app')

@section('title', 'Produk Jas')

@section('content')
<div class="space-y-6">

    {{-- ── HEADER ──────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-bold text-2xl tracking-tight" style="color: var(--text-dark)">Produk Jas</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-soft)">Kelola stok dan produk rental</p>
        </div>
@auth
@if(auth()->user()->canManage())
<div class="flex items-center gap-2">
<a href="{{ route('products.maintenance') }}" class="btn-secondary">
    <i data-lucide="wrench" class="w-4 h-4"></i>
    Maintenance
</a>
<a href="{{ route('products.create') }}" class="btn-primary">
    <i data-lucide="plus" class="w-4 h-4"></i>
    Tambah Produk
</a>
</div>
@endif
@endauth
    </div>

    {{-- ── STATS ────────────────────────────────────────────── --}}
    @php
        $totalProducts = $stats['total'] ?? 0;
        $availableProducts = $stats['available'] ?? 0;
        $maintenanceProducts = $stats['maintenance'] ?? 0;
        $rentedUnits = $stats['rented_units'] ?? 0;
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="stat-card flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5;">
                <i data-lucide="package" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-soft)">Total Produk</p>
                <p class="text-2xl font-bold mt-0.5 leading-tight" style="color:var(--text-dark)">{{ $totalProducts }}</p>
            </div>
        </div>
        <div class="stat-card flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-soft)">Tersedia</p>
                <p class="text-2xl font-bold mt-0.5 leading-tight text-green-600">{{ $availableProducts }}</p>
            </div>
        </div>
        <div class="stat-card flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: rgba(37, 99, 235, 0.12); color: #1d4ed8;">
                <i data-lucide="shirt" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-soft)">Sedang Disewa</p>
                <p class="text-2xl font-bold mt-0.5 leading-tight text-blue-600">{{ $rentedUnits }}</p>
            </div>
        </div>
        <div class="stat-card flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: rgba(220, 38, 38, 0.10); color: #dc2626;">
                <i data-lucide="wrench" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-soft)">Maintenance</p>
                <p class="text-2xl font-bold mt-0.5 leading-tight text-red-500">{{ $maintenanceProducts }}</p>
            </div>
        </div>
    </div>

    {{-- ── FILTER & SEARCH ──────────────────────────────────── --}}
    <div class="ds-card overflow-hidden mb-4">
        <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-2 gap-2 p-3 sm:flex sm:items-end sm:gap-3 sm:p-4">
            <div class="relative col-span-2 min-w-0 sm:flex-1">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Cari nama, kode produk..."
                    class="form-input ds-input-icon h-10 w-full">
            </div>
            <select name="category" class="form-input h-10 w-full min-w-0 sm:w-44">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
                @endforeach
            </select>
            <select name="status" class="form-input h-10 w-full min-w-0 sm:w-40">
                <option value="">Semua Status</option>
                <option value="available"   {{ request('status') === 'available'   ? 'selected' : '' }}>Tersedia</option>
                <option value="rented"      {{ request('status') === 'rented'      ? 'selected' : '' }}>Disewa</option>
                <option value="maintenance" {{ request('status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
            </select>
             <select name="size" class="form-input h-10 w-full min-w-0 sm:w-32">
                 <option value="">Semua Ukuran</option>
                 @foreach(($availableSizes ?? []) as $sz)
                 <option value="{{ $sz }}" {{ request('size') === $sz ? 'selected' : '' }}>{{ $sz }}</option>
                 @endforeach
             </select>
            @auth
            @if(auth()->user()->isSuperAdmin())
            <select name="branch_id" class="form-input h-10 w-full min-w-0 sm:w-44">
                <option value="">Semua Cabang</option>
                @foreach($branches ?? [] as $branch)
                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
                @endforeach
            </select>
            @endif
            @endauth
            <button type="submit" class="btn-primary flex h-10 w-full items-center justify-center whitespace-nowrap sm:w-auto">
                <i data-lucide="filter" class="w-4 h-4"></i> Filter
            </button>
            @if(request()->hasAny(['search','category','status','size']))
            <a href="{{ route('products.index') }}" class="btn-secondary flex h-10 w-full items-center justify-center whitespace-nowrap sm:w-auto">
                <i data-lucide="x" class="w-4 h-4"></i> Reset
            </a>
            @endif
        </form>
    </div>

    {{-- ── TOGGLE VIEW + COUNT ───────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <p class="text-sm" style="color:var(--text-soft)">
            {{ $products->total() }} produk ditemukan
        </p>
        <div class="flex items-center gap-1 p-1 rounded-xl" style="background:var(--secondary)">
            <button id="btn-ds-card"
                onclick="setView('ds-card')"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all"
                style="background:var(--ds-card-bg); color:var(--text-dark); box-shadow:0 1px 3px rgba(0,0,0,.08)">
                <i data-lucide="layout-grid" class="w-4 h-4"></i> Kartu
            </button>
            <button id="btn-table"
                onclick="setView('table')"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all"
                style="color:var(--text-soft)">
                <i data-lucide="table" class="w-4 h-4"></i> Tabel
            </button>
        </div>
    </div>

    @if($products->isEmpty())
    <div class="ds-card p-12 text-center">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--secondary)">
            <i data-lucide="shirt" class="w-8 h-8" style="color:var(--primary)"></i>
        </div>
        <p class="font-semibold text-lg" style="color:var(--text-dark)">Belum ada produk</p>
        <p class="text-sm mt-1 mb-4" style="color:var(--text-soft)">Tambahkan produk jas pertama Anda</p>
        @if(auth()->user()->canManage())
        <a href="{{ route('products.create') }}" class="btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Produk
        </a>
        @endif
    </div>
    @else

    <div id="view-ds-card">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @foreach($products as $product)
            <div class="ds-card overflow-hidden group flex flex-col">

                <div class="relative overflow-hidden" style="aspect-ratio: 3/4; background:var(--secondary)">
                    @if($product->photo)
                        <img src="{{ $product->photo_url }}"
                             alt="{{ $product->name }}"
                             class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center px-3 text-center"
                             style="background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(20,184,166,.10));">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center"
                                 style="background: rgba(59,130,246,.14); border: 1px solid rgba(59,130,246,.18)">
                                <i data-lucide="shirt" class="w-7 h-7" style="color:var(--primary); opacity:.55"></i>
                            </div>
                            <span class="text-xs mt-2" style="color:var(--text-soft)">Tidak ada foto</span>
                        </div>
                    @endif

                    <div class="absolute top-2 left-2">
                        @if($product->status === 'available')
                            <span class="badge badge-green text-xs">Tersedia</span>
                        @elseif($product->status === 'rented')
                            <span class="badge badge-blue text-xs">Disewa</span>
                        @elseif($product->status === 'maintenance')
                            <span class="badge badge-red text-xs">Maintenance</span>
                        @else
                            <span class="badge badge-gray text-xs">{{ $product->status }}</span>
                        @endif
                    </div>

                    <div class="absolute top-2 right-2 flex flex-col gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                        <a href="{{ route('products.show', $product) }}"
                           class="w-9 h-9 rounded-full bg-white flex items-center justify-center hover:scale-110 transition-transform"
                           title="Detail">
                            <i data-lucide="eye" class="w-4 h-4" style="color:var(--text-dark)"></i>
                        </a>
                        @if (!auth()->user()->isSales())
                        <a href="{{ route('products.edit', $product) }}"
                           class="w-9 h-9 rounded-full bg-white flex items-center justify-center hover:scale-110 transition-transform"
                           title="Edit">
                            <i data-lucide="pencil" class="w-4 h-4" style="color:var(--primary)"></i>
                        </a>
                        <form method="POST" action="{{ route('products.destroy', $product) }}" id="deleteProductCard_{{ $product->id }}" class="hidden">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDelete('deleteProductCard_{{ $product->id }}', 'produk {{ $product->name }}')"
                                class="w-9 h-9 rounded-full bg-white flex items-center justify-center hover:scale-110 transition-transform"
                                title="Hapus">
                            <i data-lucide="trash-2" class="w-4 h-4 text-red-400"></i>
                        </button>
                        @endif
                    </div>
                </div>

                <div class="p-3.5 flex flex-col gap-1.5 flex-1 min-w-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-sm leading-tight" style="color:var(--text-dark)">{{ $product->name }}</p>
                        <p class="text-xs" style="color:var(--text-soft)">{{ $product->code }}</p>
                    </div>

                    <div class="flex flex-wrap gap-1 mt-0.5">
                        @forelse($product->sizes as $size)
                        <span class="badge badge-blue-soft text-xs">{{ $size->size }}</span>
                        @empty
                        @if($product->size)
                        <span class="badge badge-blue-soft text-xs">{{ $product->size }}</span>
                        @endif
                        @endforelse
                        @if($product->color)
                        <span class="badge badge-indigo-soft text-xs">{{ $product->color }}</span>
                        @endif
                        @if($product->category)
                        <span class="badge badge-indigo-soft text-xs">{{ $product->category->name }}</span>
                        @endif
                    </div>

                    <div class="flex items-end justify-between mt-auto pt-1.5 border-t" style="border-color:var(--border)">
                        <div>
                            <p class="font-bold text-sm leading-tight" style="color:var(--text-dark)">
                                Rp {{ number_format($product->rental_price, 0, ',', '.') }}
                            </p>
                            <p class="text-xs" style="color:var(--text-soft)">/hari</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium leading-tight" style="color:var(--text-dark)">
                                {{ $product->available_stock }}/{{ $product->total_stock }}
                            </p>
                            <p class="text-xs" style="color:var(--text-soft)">stok</p>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div id="view-table" class="ds-card overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <colgroup>
                    <col style="width: 18%">
                    <col style="width: 10%">
                    <col style="width: 7%">
                    <col style="width: 8%">
                    <col style="width: 14%">
                    <col style="width: 10%">
                    <col style="width: 13%">
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 10%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Produk</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Kategori</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Ukuran</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Warna</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left hidden sm:table-cell">Cabang</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Stok</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Harga Sewa</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Status</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Kondisi</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cream-sand/30">
                    @foreach($products as $product)
                    <tr class="transition-colors">
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex-shrink-0 overflow-hidden"
                                     style="background:var(--secondary)">
                                    @if($product->photo)
                                        <img src="{{ $product->photo_url }}"
                                             alt="{{ $product->name }}"
                                             class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i data-lucide="shirt" class="w-5 h-5" style="color:var(--primary)"></i>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold text-sm" style="color:var(--text-dark)">{{ $product->name }}</p>
                                    <p class="text-xs" style="color:var(--text-soft)">{{ $product->code }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                            <span class="text-sm" style="color:var(--text-soft)">{{ $product->category?->name ?? '-' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="flex flex-wrap justify-center gap-1">
                                @forelse($product->sizes as $size)
                                    <span class="badge badge-blue-soft text-xs">{{ $size->size }}</span>
                                @empty
                                    <span class="badge badge-gray text-xs">{{ $product->size ?? '-' }}</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <span class="text-sm" style="color:var(--text-soft)">{{ $product->color ?? '-' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center hidden sm:table-cell">
                            @foreach($product->branches as $branch)
                                <span class="inline-block rounded-full bg-blue-50 text-blue-700 text-xs px-2 py-0.5 mb-1">{{ $branch->name }}</span>
                            @endforeach
                            @if($product->branches->isEmpty())
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="text-sm font-medium" style="color:var(--text-dark)">
                                {{ $product->available_stock }}/{{ $product->total_stock }}
                            </div>
                            <div class="text-xs" style="color:var(--text-soft)">tersedia</div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-right">
                            <span class="font-semibold text-sm" style="color:var(--text-dark)">
                                Rp {{ number_format($product->rental_price, 0, ',', '.') }}
                            </span>
                            <div class="text-xs" style="color:var(--text-soft)">/hari</div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="flex justify-center">
                            @if($product->status === 'available')
                                <span class="badge badge-green">Tersedia</span>
                            @elseif($product->status === 'rented')
                                <span class="badge badge-blue">Disewa</span>
                            @elseif($product->status === 'maintenance')
                                <span class="badge badge-red">Maintenance</span>
                            @else
                                <span class="badge badge-gray">{{ $product->status }}</span>
                            @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="flex justify-center">
                            @if($product->condition === 'excellent')
                                <span class="badge badge-gold">Excellent</span>
                            @elseif($product->condition === 'good')
                                <span class="badge badge-green">Good</span>
                            @elseif($product->condition === 'fair')
                                <span class="badge badge-gold">Fair</span>
                            @else
                                <span class="badge badge-red">{{ $product->condition ?? '-' }}</span>
                            @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('products.show', $product) }}"
                                   class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" title="Detail">
                                    <i data-lucide="eye" class="w-4 h-4" style="color:var(--text-soft)"></i>
                                </a>
                                @if (!auth()->user()->isSales())
                                <a href="{{ route('products.edit', $product) }}"
                                   class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4" style="color:var(--primary)"></i>
                                </a>
                                <form method="POST" action="{{ route('products.destroy', $product) }}" id="deleteProductTable_{{ $product->id }}" class="hidden">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDelete('deleteProductTable_{{ $product->id }}', 'produk {{ $product->name }}')"
                                        class="p-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                    <i data-lucide="trash-2" class="w-4 h-4 text-red-400"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($products->hasPages())
    <div class="px-6 py-4 border-t" style="border-color:var(--border)">
        {{ $products->links('components.pagination') }}
    </div>
    @endif
    @endif
</div>

@push('scripts')
<script>
function setView(view) {
    const cardView = document.getElementById('view-ds-card');
    const tableView = document.getElementById('view-table');
    const btnCard = document.getElementById('btn-ds-card');
    const btnTable = document.getElementById('btn-table');

    if (!cardView || !tableView) return;

    if (view === 'ds-card') {
        cardView.classList.remove('hidden');
        tableView.classList.add('hidden');
        btnCard.style.background = 'var(--ds-card-bg)';
        btnCard.style.color = 'var(--text-dark)';
        btnCard.style.boxShadow = '0 1px 3px rgba(0,0,0,.08)';
        btnTable.style.background = 'transparent';
        btnTable.style.color = 'var(--text-soft)';
        btnTable.style.boxShadow = 'none';
        localStorage.setItem('productView', 'ds-card');
    } else {
        cardView.classList.add('hidden');
        tableView.classList.remove('hidden');
        btnTable.style.background = 'var(--ds-card-bg)';
        btnTable.style.color = 'var(--text-dark)';
        btnTable.style.boxShadow = '0 1px 3px rgba(0,0,0,.08)';
        btnCard.style.background = 'transparent';
        btnCard.style.color = 'var(--text-soft)';
        btnCard.style.boxShadow = 'none';
        localStorage.setItem('productView', 'table');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('productView') || 'ds-card';
    setView(savedView);
});
</script>
@endpush
@endsection
