{{-- resources/views/reports/partials/stock-content.blade.php --}}

{{-- ── SUMMARY CARDS ───────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
    <div class="card p-5 flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:var(--secondary)">
            <i data-lucide="shirt" class="w-5 h-5" style="color:var(--primary)"></i>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-soft)">Total Produk</p>
            <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $totalProducts }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:#10B98115">
            <i data-lucide="package" class="w-5 h-5" style="color:#10B981"></i>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-soft)">Total Stok</p>
            <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $totalStock }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:#3B82F615">
            <i data-lucide="check-circle" class="w-5 h-5" style="color:#3B82F6"></i>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-soft)">Stok Tersedia</p>
            <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $totalAvail }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:#F59E0B15">
            <i data-lucide="arrow-up-right" class="w-5 h-5" style="color:#F59E0B"></i>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-soft)">Sedang Disewa</p>
            <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $activeRentals }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:#EF444415">
            <i data-lucide="alert-triangle" class="w-5 h-5" style="color:#EF4444"></i>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-soft)">Stok Habis</p>
            <p class="text-2xl font-bold font-playfair" style="color:var(--text-dark)">{{ $outOfStock }}</p>
        </div>
    </div>
</div>

{{-- Low Stock Alert --}}
@if($lowStock > 0)
<div class="card p-4 flex items-center gap-3 border-l-4" style="border-left-color:#F59E0B">
    <i data-lucide="alert-triangle" class="w-5 h-5" style="color:#F59E0B"></i>
    <p class="text-sm" style="color:var(--text-dark)">
        <span class="font-semibold">{{ $lowStock }}</span> produk hampir habis stoknya (sisa ≤ 2 unit)
    </p>
</div>
@endif

{{-- ── FILTER ─────────────────────────────────────────────── --}}
<div class="card p-5">
    <form method="GET" action="{{ route('reports.stock') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Cari Produk</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Nama / kode..." class="form-input">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1" style="color:var(--text-soft)">Kategori</label>
            <select name="category" class="form-input">
                <option value="">Semua Kategori</option>
                @foreach($categories as $k)
                    <option value="{{ $k->id }}" {{ $category == $k->id ? 'selected' : '' }}>{{ $k->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">
            <i data-lucide="search" class="w-4 h-4"></i> Filter
        </button>
        <a href="{{ route('reports.stock') }}" class="btn-secondary">Reset</a>
    </form>
</div>

{{-- ── TABEL STOK ──────────────────────────────────────────── --}}
<div class="ds-card overflow-hidden">
    <div class="px-5 py-4 border-b" style="border-color:var(--border)">
        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Daftar Stok Produk</h2>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <colgroup>
            <col style="width: 5%">
            <col style="width: 20%">
            <col style="width: 12%">
            <col style="width: 14%">
            <col style="width: 10%">
            <col style="width: 12%">
            <col style="width: 17%">
            <col style="width: 10%">
        </colgroup>
        <thead>
            <tr>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">#</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Nama Produk</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Kode</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-left">Kategori</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Stok Total</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Tersedia</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Kondisi</th>
                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cream-sand/30">
            @forelse($products as $i => $product)
            <tr class="transition-colors">
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center text-xs" style="color:var(--text-soft)">{{ $products->firstItem() + $i }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle">
                    <p class="font-medium text-sm" style="color:var(--text-dark)">{{ $product->name }}</p>
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle font-mono text-xs" style="color:var(--text-soft)">{{ $product->code ?? '-' }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-sm" style="color:var(--text-soft)">{{ $product->category?->name ?? '-' }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center font-semibold text-sm" style="color:var(--text-dark)">{{ $product->stock_total }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                    <span class="font-bold text-lg font-playfair {{ $product->stock_available == 0 ? 'text-red-500' : ($product->stock_available <= 2 ? 'text-yellow-500' : 'stock-available-normal') }}">
                        {{ $product->stock_available }}
                    </span>
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-700 align-middle text-center">
                    <div class="flex justify-center">
                    @if($product->stock_available == 0)
                        <span class="badge badge-red">Habis</span>
                    @elseif($product->stock_available <= 2)
                        <span class="badge badge-gold">Hampir Habis</span>
                    @else
                        <span class="badge badge-green">Tersedia</span>
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
                        <form method="POST" action="{{ route('products.destroy', $product) }}" id="deleteProductStock_{{ $product->id }}" class="hidden">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDelete('deleteProductStock_{{ $product->id }}', 'produk {{ $product->name }}')"
                                class="p-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                            <i data-lucide="trash-2" class="w-4 h-4 text-red-400"></i>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-5 py-12 text-center">
                    <i data-lucide="package-x" class="w-8 h-8 mx-auto mb-2" style="color:var(--border)"></i>
                    <p class="text-sm" style="color:var(--text-soft)">Tidak ada data produk</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($products->hasPages())
    <div class="px-6 py-4 border-t" style="border-color:var(--border)">
        {{ $products->appends(request()->query())->links('components.pagination') }}
    </div>
    @endif
</div>
