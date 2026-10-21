@extends('Layouts.app')

@section('title', 'Tambah Produk Jas')

@section('content')
<div class="space-y-6">

    {{-- ── HEADER ──────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('products.index') }}" class="btn-secondary p-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <div>
                <h1 class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">Tambah Produk Baru</h1>
                <p class="text-sm mt-0.5" style="color: var(--text-soft)">Isi detail produk jas untuk rental</p>
            </div>
        </div>
    </div>

    {{-- ── FORM ─────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ── KOLOM KIRI (2/3) ─────────────────────────── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Informasi Dasar --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="info" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Informasi Dasar</h2>
                    </div>

                    {{-- Nama Produk --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                            Nama Produk <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               placeholder="Contoh: Jas Formal Classic Black"
                               class="form-input @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Kode & Kategori --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Kode Produk
                            </label>
                            <input type="text" value="{{ $nextCode }}" disabled
                                   class="form-input cursor-not-allowed bg-slate-100 text-slate-500">
                            <p class="text-xs mt-1" style="color:var(--text-soft)">Kode dibuat otomatis saat produk disimpan.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Kategori <span class="text-red-500">*</span>
                            </label>
                            <select name="category_id" class="form-input @error('category_id') border-red-400 @enderror">
                                <option value="">Pilih Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Deskripsi</label>
                        <textarea name="description" rows="4"
                                  placeholder="Deskripsi singkat tentang produk..."
                                  class="form-input resize-none @error('description') border-red-400 @enderror">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if(auth()->user()->isSuperAdmin())
                {{-- Cabang --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="navigation" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Cabang Tersedia</h2>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" id="select-all-branches" class="rounded text-blue-600 focus:ring-blue-500" checked>
                            <span class="text-sm font-medium" style="color:var(--text-dark)">Pilih Semua Cabang</span>
                        </label>
                        @foreach($branches as $branch)
                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" class="branch-checkbox rounded text-blue-600 focus:ring-blue-500" checked>
                            <span class="text-sm" style="color:var(--text-dark)">{{ $branch->name }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('branch_ids')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                {{-- Detail Fisik --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="shirt" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Detail Fisik</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Warna --}}
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Warna</label>
                            <input type="text" name="color" value="{{ old('color') }}"
                                   placeholder="Contoh: Hitam, Navy"
                                   class="form-input @error('color') border-red-400 @enderror">
                            @error('color')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Kondisi --}}
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Kondisi</label>
                            <select name="condition" class="form-input @error('condition') border-red-400 @enderror">
                                <option value="">Pilih Kondisi</option>
                                <option value="excellent" {{ old('condition') === 'excellent' ? 'selected' : '' }}>Excellent</option>
                                <option value="good"      {{ old('condition') === 'good'      ? 'selected' : '' }}>Good</option>
                                <option value="fair"      {{ old('condition') === 'fair'      ? 'selected' : '' }}>Fair</option>
                                <option value="poor"      {{ old('condition') === 'poor'      ? 'selected' : '' }}>Poor</option>
                            </select>
                            @error('condition')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Ukuran & Stok --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="ruler" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Ukuran & Stok</h2>
                    </div>

                    <div x-data='sizeForm({!! json_encode(old("sizes", []), JSON_UNESCAPED_UNICODE) !!})'>
                        {{-- Size checkbox grid — pill, 2 col mobile / 4 col desktop, gap rapi --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <template x-for="sz in allSizes" :key="sz">
                                <label class="inline-flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 cursor-pointer transition-colors"
                                       :class="selected.includes(sz) ? 'bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-50'">
                                    <input type="checkbox"
                                           :value="sz"
                                           x-model="selected"
                                           @change="onToggle(sz, $event)"
                                           class="rounded w-4 h-4 shrink-0 text-blue-600 border-slate-300 focus:ring-blue-500 focus:ring-2">
                                    <span class="text-sm font-medium leading-none" :style="selected.includes(sz) ? 'color:#1d4ed8' : 'color:var(--text-dark)'" x-text="sz"></span>
                                </label>
                            </template>
                        </div>
                        @error('sizes')
                            <p class="text-xs text-red-500 mt-2">{{ $message }}</p>
                        @enderror

                        {{-- Dynamic stock cards — 2 kolom sejajar, padding konsisten --}}
                        <div x-show="selected.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mt-5" x-cloak>
                            <template x-for="sz in selected" :key="sz">
                                <div class="border border-slate-200 rounded-2xl p-4 bg-[#f8fafc]">
                                    <h4 class="font-bold text-[15px] leading-none mb-3.5" style="color:var(--text-dark)" x-text="sz"></h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex flex-col">
                                            <label class="flex items-end min-h-[34px] pb-1.5 text-[11px] font-semibold tracking-wide uppercase leading-none" style="color:var(--text-soft)">Stok Total</label>
                                            <input type="number" min="0"
                                                   :name="`sizes[${sz}][stock_total]`"
                                                   x-model.number="stocks[sz].stock_total"
                                                   class="form-input !min-h-0 !h-[42px] !py-0 !px-3 text-sm font-semibold">
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="flex flex-col justify-end min-h-[34px] pb-1.5 text-[11px] font-semibold tracking-wide uppercase leading-none" style="color:var(--text-soft)">
                                                <span class="leading-none">Stok Tersedia</span>
                                                <span class="normal-case font-medium tracking-normal text-[10px] leading-none mt-0.5">(otomatis)</span>
                                            </label>
                                            <input type="number" min="0"
                                                   :name="`sizes[${sz}][stock_available]`"
                                                   x-model.number="stocks[sz].stock_available"
                                                   class="form-input !min-h-0 !h-[42px] !py-0 !px-3 text-sm font-semibold">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p x-show="selected.length === 0" class="mt-4 text-xs text-center py-3 rounded-xl border border-dashed border-slate-200 bg-slate-50" style="color:var(--text-soft)">Pilih ukuran di atas untuk mengatur stok</p>
                    </div>
                </div>

                {{-- Harga --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="package" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Stok & Harga</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Harga Sewa --}}
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Harga Sewa/Hari <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium" style="color:var(--text-soft)">Rp</span>
                                <input type="number" name="rental_price" value="{{ old('rental_price') }}" min="0"
                                       placeholder="0"
                                       class="form-input @error('rental_price') border-red-400 @enderror" style="padding-left: 40px !important">
                            </div>
                            @error('rental_price')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Poin Sales --}}
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Poin Sales <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium" style="color:var(--text-soft)">★</span>
                                <input type="number" name="points" value="{{ old('points', 3) }}" min="0" step="1" required
                                       placeholder="3"
                                       class="form-input @error('points') border-red-400 @enderror" style="padding-left: 40px !important">
                            </div>
                            <p class="text-xs mt-1" style="color:var(--text-soft)">Poin untuk sales per serah &amp; pengembalian (× jumlah barang).</p>
                            @error('points')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── KOLOM KANAN (1/3) ────────────────────────── --}}
            <div class="space-y-6">

                {{-- Foto Produk --}}
                <div class="card p-6 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="image" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Foto Produk</h2>
                    </div>

                    <div id="drop-zone"
                         class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors"
                         style="border-color:var(--border)"
                         onclick="document.getElementById('image-input').click()">
                        <div id="preview-wrap" class="hidden mb-3">
                            <img id="preview-img" src="#" alt="Preview" class="w-full h-40 object-cover rounded-lg">
                        </div>
                        <div id="upload-placeholder">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3" style="background:var(--secondary)">
                                <i data-lucide="upload-cloud" class="w-6 h-6" style="color:var(--primary)"></i>
                            </div>
                            <p class="text-sm font-medium" style="color:var(--text-dark)">Klik atau seret foto ke sini</p>
                            <p class="text-xs mt-1" style="color:var(--text-soft)">PNG, JPG, WEBP — Maks. 2MB, foto besar otomatis dikompres</p>
                        </div>
                        <input type="file" id="image-input" name="image" accept="image/*" class="hidden">
                    </div>
                    @error('image')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status & Publish --}}
                <div class="card p-6 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="toggle-right" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Status Produk</h2>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Status</label>
                        <select name="status" class="form-input @error('status') border-red-400 @enderror">
                            <option value="available"   {{ old('status','available') === 'available'   ? 'selected' : '' }}>Tersedia</option>
                            <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                        </select>
                        @error('status')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Catatan --}}
                <div class="card p-6 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="file-text" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Catatan Internal</h2>
                    </div>
                    <textarea name="notes" rows="3"
                              placeholder="Catatan khusus tentang produk ini..."
                              class="form-input resize-none text-sm">{{ old('notes') }}</textarea>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full justify-center">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Produk
                    </button>
                    <a href="{{ route('products.index') }}" class="btn-secondary w-full justify-center text-center">
                        Batal
                    </a>
                </div>
            </div>

        </div>
    </form>
</div>

@push('scripts')
<script>
    lucide.createIcons();

    // Image preview
    const input    = document.getElementById('image-input');
    const preview  = document.getElementById('preview-img');
    const prevWrap = document.getElementById('preview-wrap');
    const placeholder = document.getElementById('upload-placeholder');
    const dropZone = document.getElementById('drop-zone');

    input.addEventListener('change', function () {
        handleFile(this.files[0]);
    });

    // Drag & drop
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor = 'var(--primary)'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = 'var(--border)'; });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--border)';
        handleFile(e.dataTransfer.files[0]);
    });

    // Kompres bila perlu (>2MB), sinkronkan hasilnya ke input, lalu preview
    async function handleFile(file) {
        if (!file) return;
        const finalFile = window.compressAndAssign
            ? await window.compressAndAssign(input, file)
            : file;
        showPreview(finalFile);
    }

    function showPreview(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src     = e.target.result;
            prevWrap.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }

    // Select all branches toggle
    const selectAll = document.getElementById('select-all-branches');
    const branchCheckboxes = document.querySelectorAll('.branch-checkbox');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            branchCheckboxes.forEach(cb => cb.checked = this.checked);
        });
        branchCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                selectAll.checked = Array.from(branchCheckboxes).every(c => c.checked);
            });
        });
    }

    // Size & Stock dynamic form (Alpine.js)
    function sizeForm(initialStocks) {
        return {
            allSizes: ['XS','S','M','L','XL','XXL','3XL','4XL','5XL','6XL','7XL'],
            selected: Object.keys(initialStocks || {}),
            stocks: initialStocks || {},

            onToggle(sz, event) {
                if (event.target.checked) {
                    if (!this.stocks[sz]) {
                        this.stocks[sz] = { stock_total: 0, stock_available: 0 };
                    }
                } else {
                    delete this.stocks[sz];
                }
            }
        };
    }
</script>
@endpush
@endsection
