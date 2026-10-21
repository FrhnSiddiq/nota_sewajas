@extends('Layouts.app')

@section('title', 'Edit Produk — ' . $product->name)

@section('content')
<div class="space-y-6">

    {{-- ── HEADER ──────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('products.show', $product) }}" class="btn-secondary p-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <div>
                <h1 class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">Edit Produk</h1>
                <p class="text-sm mt-0.5" style="color: var(--text-soft)">{{ $product->code }} · {{ $product->name }}</p>
            </div>
        </div>
    </div>

    {{-- ── FORM ─────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

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

                    {{-- Nama --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                            Nama Produk <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}"
                               class="form-input @error('name') border-red-400 @enderror">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- Kode & Kategori --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Kode Produk
                            </label>
                            <input type="text" value="{{ $product->code }}" disabled
                                   class="form-input cursor-not-allowed bg-slate-100 text-slate-500">
                            <p class="text-xs mt-1" style="color:var(--text-soft)">Kode produk dibuat otomatis dan tidak dapat diubah.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Kategori <span class="text-red-500">*</span>
                            </label>
                            <select name="category_id" class="form-input @error('category_id') border-red-400 @enderror">
                                <option value="">Pilih Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Deskripsi</label>
                        <textarea name="description" rows="4"
                                  class="form-input resize-none @error('description') border-red-400 @enderror">{{ old('description', $product->description) }}</textarea>
                        @error('description')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
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
                            <input type="checkbox" id="select-all-branches-edit" class="rounded text-blue-600 focus:ring-blue-500" {{ count($selectedBranches) === $branches->count() ? 'checked' : '' }}>
                            <span class="text-sm font-medium" style="color:var(--text-dark)">Pilih Semua Cabang</span>
                        </label>
                        @foreach($branches as $branch)
                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" class="branch-checkbox-edit rounded text-blue-600 focus:ring-blue-500" {{ in_array($branch->id, old('branch_ids', $selectedBranches)) ? 'checked' : '' }}>
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
                            <input type="text" name="color" value="{{ old('color', $product->color) }}"
                                   class="form-input @error('color') border-red-400 @enderror">
                            @error('color')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Kondisi --}}
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Kondisi</label>
                            <select name="condition" class="form-input @error('condition') border-red-400 @enderror">
                                <option value="">Pilih Kondisi</option>
                                @foreach(['excellent','good','fair','poor'] as $cond)
                                    <option value="{{ $cond }}"
                                        {{ old('condition', $product->condition) === $cond ? 'selected' : '' }}>
                                        {{ ucfirst($cond) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('condition')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
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

                    <div x-data='sizeForm({!! json_encode($initialSizes ?? [], JSON_UNESCAPED_UNICODE) !!}, {!! json_encode($rentedBySize ?? [], JSON_UNESCAPED_UNICODE) !!})'>
                        {{-- Size checkbox grid — pill 2/4 col, selected biru --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <template x-for="sz in allSizes" :key="sz">
                                <label class="inline-flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 cursor-pointer transition-colors"
                                       :class="[
                                           Number(rentedBySize[sz] || 0) > 0 ? 'bg-slate-100 border-slate-200 opacity-60 cursor-not-allowed' : (selected.includes(sz) ? 'bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-50')
                                       ]">
                                    <input type="checkbox"
                                           :value="sz"
                                           x-model="selected"
                                           :disabled="Number(rentedBySize[sz] || 0) > 0"
                                           @change="onToggle(sz, $event)"
                                           class="rounded w-4 h-4 shrink-0 text-blue-600 border-slate-300 focus:ring-blue-500 focus:ring-2">
                                    <span class="text-sm font-medium leading-none" :style="selected.includes(sz) ? 'color:#1d4ed8' : 'color:var(--text-dark)'" x-text="sz"></span>
                                    <span x-show="Number(rentedBySize[sz] || 0) > 0" class="ml-auto text-[11px] font-bold text-amber-600" x-text="`×${rentedBySize[sz]}`"></span>
                                </label>
                            </template>
                        </div>
                        @error('sizes')
                            <p class="text-xs text-red-500 mt-2">{{ $message }}</p>
                        @enderror

                        {{-- Dynamic stock cards — 2 kolom, padding konsisten --}}
                        <div x-show="selected.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mt-5" x-cloak>
                            <template x-for="sz in selected" :key="sz">
                                <div class="border border-slate-200 rounded-2xl p-4 bg-[#f8fafc]">
                                    <h4 class="font-bold text-[15px] leading-none mb-3.5" style="color:var(--text-dark)" x-text="sz"></h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex flex-col">
                                            <label class="flex items-end min-h-[34px] pb-1.5 text-[11px] font-semibold tracking-wide uppercase leading-none" style="color:var(--text-soft)">Stok Total</label>
                                            <input type="number" :min="Number(rentedBySize[sz] || 0)"
                                                   :name="`sizes[${sz}][stock_total]`"
                                                   :value="stocks[sz].stock_total"
                                                   @input="updateAvailableStock(sz, $event.target.value)"
                                                   class="form-input !min-h-0 !h-[42px] !py-0 !px-3 text-sm font-semibold">
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="flex flex-col justify-end min-h-[34px] pb-1.5 text-[11px] font-semibold tracking-wide uppercase leading-none" style="color:var(--text-soft)">
                                                <span class="leading-none">Stok Tersedia</span>
                                                <span class="normal-case font-medium tracking-normal text-[10px] leading-none mt-0.5">(otomatis)</span>
                                            </label>
                                            <input type="number" min="0"
                                                   :name="`sizes[${sz}][stock_available]`"
                                                   :value="stocks[sz].stock_available"
                                                   readonly
                                                   class="form-input !min-h-0 !h-[42px] !py-0 !px-3 text-sm font-semibold cursor-not-allowed !bg-slate-100">
                                        </div>
                                    </div>
                                    <p x-show="Number(rentedBySize[sz] || 0) > 0" class="mt-2.5 text-[11px] leading-relaxed px-2.5 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700">
                                        <span x-text="`${rentedBySize[sz]} unit sedang disewa dan tetap dihitung sebagai stok terpakai.`"></span>
                                    </p>
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
                                <input type="number" name="rental_price" min="0"
                                       value="{{ old('rental_price', $product->rental_price) }}"
                                       class="form-input @error('rental_price') border-red-400 @enderror" style="padding-left: 40px !important">
                            </div>
                            @error('rental_price')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Poin Sales --}}
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                                Poin Sales <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium" style="color:var(--text-soft)">★</span>
                                <input type="number" name="points" value="{{ old('points', $product->points) }}" min="0" step="1" required
                                       placeholder="3"
                                       class="form-input @error('points') border-red-400 @enderror" style="padding-left: 40px !important">
                            </div>
                            <p class="text-xs mt-1" style="color:var(--text-soft)">Poin untuk sales per serah &amp; pengembalian (× jumlah barang).</p>
                            @error('points')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── KOLOM KANAN (1/3) ────────────────────────── --}}
            <div class="space-y-6">

                {{-- Foto Produk --}}
                <div class="card p-6 space-y-4" x-data="productPhotoForm()">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="image" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Foto Produk</h2>
                    </div>

                    <div x-show="photoPreview || hasCurrentPhoto" class="relative mx-auto w-full max-w-xs aspect-[3/4] overflow-hidden rounded-xl">
                        <img :src="photoPreview || currentPhotoUrl" class="absolute inset-0 w-full h-full object-cover" alt="Preview foto produk">

                        <button type="button" @click="clearPhoto()"
                                class="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center"
                                style="background: rgba(0,0,0,0.5)">
                            <i data-lucide="x" class="w-3.5 h-3.5 text-white"></i>
                        </button>

                        <button type="button" @click="$refs.photoInput.click()"
                                class="absolute bottom-2 right-2 btn-secondary text-xs px-2 py-1">
                            Ganti Foto
                        </button>
                    </div>

                    <div x-show="!photoPreview && !hasCurrentPhoto"
                         class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors"
                         style="border-color:var(--border)"
                         @click="$refs.photoInput.click()"
                         @dragover.prevent="isDragging = true"
                         @dragenter.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="isDragging = false; handleDrop($event)"
                         :class="isDragging ? 'border-blue-500 bg-blue-50' : ''">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3" style="background:var(--secondary)">
                            <i data-lucide="upload-cloud" class="w-6 h-6" style="color:var(--primary)"></i>
                        </div>
                        <p class="text-sm font-medium" style="color:var(--text-dark)">Klik atau seret foto ke sini</p>
                        <p class="text-xs mt-1" style="color:var(--text-soft)">PNG, JPG, WEBP — Maks. 2MB, foto besar otomatis dikompres</p>
                    </div>

                     <input type="file" x-ref="photoInput" name="image" accept="image/*" class="hidden"
                            @change="handlePhotoSelect($event)">
                     <input type="hidden" name="remove_photo" value="0">
                     @error('image')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                 </div>

                {{-- Status --}}
                <div class="card p-6 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="toggle-right" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Status Produk</h2>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Status</label>
                        @if($product->status === 'rented')
                            <div class="form-input bg-slate-100 text-slate-500 cursor-not-allowed flex items-center justify-between">
                                <span>Disewa</span>
                                <span class="text-xs font-medium" style="color:var(--text-soft)">Otomatis — sedang dalam transaksi aktif</span>
                            </div>
                            <p class="text-xs mt-1" style="color:var(--text-soft)">Status otomatis berdasarkan rental aktif dan tidak dapat diubah manual.</p>
                        @else
                            <select name="status" class="form-input @error('status') border-red-400 @enderror">
                                <option value="available"   {{ old('status', $product->status) === 'available'   ? 'selected' : '' }}>Tersedia</option>
                                <option value="maintenance" {{ old('status', $product->status) === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                <option value="inactive"    {{ old('status', $product->status) === 'inactive'    ? 'selected' : '' }}>Tidak Aktif</option>
                            </select>
                            @error('status')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        @endif
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
                              class="form-input resize-none text-sm">{{ old('notes', $product->notes) }}</textarea>
                </div>

                {{-- Last updated info --}}
                <div class="card p-4">
                    <p class="text-xs" style="color:var(--text-soft)">
                        <i data-lucide="clock" class="w-3 h-3 inline-block mr-1"></i>
                        Diperbarui: {{ $product->updated_at->diffForHumans() }}
                    </p>
                    <p class="text-xs mt-1" style="color:var(--text-soft)">
                        <i data-lucide="calendar" class="w-3 h-3 inline-block mr-1"></i>
                        Dibuat: {{ $product->created_at->format('d M Y') }}
                    </p>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full justify-center">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Perubahan
                    </button>
                    <a href="{{ route('products.show', $product) }}" class="btn-secondary w-full justify-center text-center">
                        Batal
                    </a>
                </div>
            </div>

        </div>
    </form>
</div>

@push('scripts')
<script>
function productPhotoForm() {
    return {
        photoPreview: null,
        hasCurrentPhoto: {{ $product->photo ? 'true' : 'false' }},
        currentPhotoUrl: '{{ $product->photo ? asset("storage/".$product->photo) : "" }}',
        isDragging: false,

        async handlePhotoSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            await this.applyPhoto(file);
        },

        async handleDrop(event) {
            const file = event.dataTransfer.files[0];
            if (!file) return;
            await this.applyPhoto(file);
        },

        // Kompres bila perlu + sinkronkan file hasilnya ke input.
        // (Jalur drop dulu tidak pernah mengisi input sehingga foto hasil drop
        // tidak terkirim — kini ikut terkirim seperti jalur pemilihan file.)
        async applyPhoto(file) {
            const finalFile = window.compressAndAssign
                ? await window.compressAndAssign(this.$refs.photoInput, file)
                : file;
            const reader = new FileReader();
            reader.onload = e => {
                this.photoPreview = e.target.result;
                this.hasCurrentPhoto = true;
            };
            reader.readAsDataURL(finalFile);
        },

        clearPhoto() {
            this.photoPreview = null;
            this.hasCurrentPhoto = false;
            this.$refs.photoInput.value = '';
            const removeInput = document.querySelector('input[name="remove_photo"]');
            if (removeInput) removeInput.value = '1';
        },
    };
}

// Select all branches toggle
const selectAllEdit = document.getElementById('select-all-branches-edit');
const branchCheckboxesEdit = document.querySelectorAll('.branch-checkbox-edit');
if (selectAllEdit) {
    selectAllEdit.addEventListener('change', function() {
        branchCheckboxesEdit.forEach(cb => cb.checked = this.checked);
    });
    branchCheckboxesEdit.forEach(cb => {
        cb.addEventListener('change', function() {
            selectAllEdit.checked = Array.from(branchCheckboxesEdit).every(c => c.checked);
        });
    });
}

// Size & Stock dynamic form (Alpine.js)
function sizeForm(initialStocks, rentedBySize) {
    return {
        allSizes: ['XS','S','M','L','XL','XXL','3XL','4XL','5XL','6XL','7XL'],
        selected: Object.keys(initialStocks || {}),
        stocks: initialStocks || {},
        rentedBySize: rentedBySize || {},

        init() {
            Object.keys(this.stocks).forEach(sz => {
                this.updateAvailableStock(sz, this.stocks[sz].stock_total);
            });
        },

        updateAvailableStock(sz, total) {
            const parsedTotal = Number.parseInt(total, 10);
            if (!Number.isFinite(parsedTotal)) return;

            this.stocks[sz].stock_total = parsedTotal;
            this.stocks[sz].stock_available = Math.max(
                0,
                parsedTotal - Number(this.rentedBySize[sz] || 0)
            );
        },

        onToggle(sz, event) {
            if (event.target.checked) {
                if (!this.stocks[sz]) {
                    this.stocks[sz] = { stock_total: 0, stock_available: 0 };
                    this.rentedBySize[sz] = 0;
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
