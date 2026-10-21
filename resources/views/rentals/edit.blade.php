@extends('Layouts.app')

@section('title', 'Edit Penyewaan')
@section('page-title', 'Edit Penyewaan')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('rentals.show', $rental) }}" class="btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali ke Detail Penyewaan
        </a>
    </div>
    @php
        $isFormLocked = $rental->payment_status === \App\Models\Rental::PAYMENT_PAID
            || $rental->rental_status === \App\Models\Rental::STATUS_ACTIVE;
    @endphp

    @if($isFormLocked)
    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800 flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <span>Form ini dikunci karena penyewaan sudah <strong>{{ $rental->payment_status === 'paid' ? 'dibayar' : 'berjalan' }}</strong>. Beberapa field tidak dapat diubah.</span>
    </div>
    @endif

    <form method="POST" action="{{ route('rentals.update', $rental) }}" enctype="multipart/form-data" x-data="rentalForm({{ $rental->id }}, {{ $isFormLocked ? 'true' : 'false' }})" @submit.prevent="submitForm()">
        @csrf
        @method('PATCH')

        <!-- Customer & Rental Info -->
        <div class="card p-6">
            <h3 class="font-playfair font-semibold text-base mb-5" style="color: var(--text-dark)">
                <i data-lucide="user" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Informasi Pelanggan & Penyewaan
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Pelanggan <span class="text-red-400">*</span></label>
                    <div class="relative" @click.outside="customerDropdownOpen = false">
                        <input type="text"
                            x-model="customerSearch"
                            @focus="customerDropdownOpen = true"
                            @click="customerDropdownOpen = true"
                            @input="selectedCustomer = null; customerDropdownOpen = true"
                            class="form-input"
                            placeholder="Pilih pelanggan atau ketik untuk mencari..."
                            autocomplete="off"
                            :disabled="formLocked"
                            title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}"
                        >
                        <input type="hidden" name="customer_id" :value="selectedCustomer?.id ?? '{{ $rental->customer_id }}'" required>

                        <div x-show="customerDropdownOpen"
                             x-transition
                             class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-auto"
                             style="display: none;">
                            <template x-if="filteredCustomers.length > 0">
                                <template x-for="customer in dropdownCustomers" :key="customer.id">
                                    <div @click="selectCustomer(customer)"
                                         class="px-4 py-2.5 cursor-pointer hover:bg-blue-50 text-sm flex items-center justify-between"
                                         :class="selectedCustomer?.id === customer.id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-slate-700'">
                                        <span x-text="customer.name"></span>
                                        <span class="text-xs text-slate-400" x-text="customer.phone"></span>
                                    </div>
                                </template>
                            </template>

                            <div x-show="filteredCustomers.length > dropdownCustomers.length"
                                 class="px-4 py-2 text-xs text-slate-400 border-t border-slate-100 bg-slate-50/60 text-center">
                                Menampilkan <span x-text="dropdownCustomers.length"></span> dari <span x-text="filteredCustomers.length"></span> pelanggan — ketik untuk mempersempit
                            </div>

                            <div x-show="!customerSearch && filteredCustomers.length === 0"
                                 class="px-4 py-3 text-sm text-slate-400">
                                Belum ada pelanggan terdaftar.
                            </div>

                            <div x-show="customerSearch && filteredCustomers.length === 0"
                                 @click="openAddModal()"
                                 class="px-4 py-3 cursor-pointer hover:bg-amber-50 text-sm text-amber-700 border-t border-slate-100 flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Tambah '<span x-text="customerSearch"></span>' sebagai pelanggan baru</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Tambah Pelanggan Baru -->
                <div x-show="showAddModal" x-transition class="fixed inset-0 z-[100] flex items-center justify-center" style="display: none;">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showAddModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-auto" @click.stop>
                        <div class="flex items-center justify-between p-6 border-b border-slate-100">
                            <h3 class="font-semibold text-base text-slate-800">Tambah Pelanggan Baru</h3>
                            <button type="button" @click="showAddModal = false" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                            <form @submit.prevent="addCustomer()" class="p-6 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1.5 text-slate-700">Nama Lengkap <span class="text-red-400">*</span></label>
                                    <input type="text" x-model="newCustomer.name" class="form-input" :required="showAddModal">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1.5 text-slate-700">Nomor Handphone <span class="text-red-400">*</span></label>
                                    <div class="input-group-phone">
                                        <span class="input-group-phone__prefix">+62</span>
                                        <input type="text"
                                            x-model="newCustomer.phone"
                                            class="input-group-phone__input"
                                            placeholder="8123456789"
                                            :required="showAddModal"
                                            inputmode="numeric"
                                            @input="filterPhoneInput($event)"
                                            @keydown="preventNonNumeric($event)"
                                            autocomplete="tel"
                                        >
                                    </div>
                                </div>
                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button type="button" @click="showAddModal = false" class="btn-secondary px-5 py-2.5" :disabled="addingCustomer">Batal</button>
                                <button type="submit" class="btn-primary px-5 py-2.5" :disabled="addingCustomer">
                                    <span x-show="!addingCustomer">Simpan Pelanggan</span>
                                    <span x-show="addingCustomer" class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Menyimpan...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Tanggal Sewa <span class="text-red-400">*</span></label>
                    <input type="date" name="rental_date" value="{{ old('rental_date', optional($rental->rental_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="form-input" required :disabled="formLocked" title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Tanggal Pengembalian</label>
                    <input type="date" name="return_due_date" value="{{ old('return_due_date', optional($rental->return_due_date)->format('Y-m-d')) }}" class="form-input" :disabled="formLocked" title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Durasi (Hari) <span class="text-red-400">*</span></label>
                    <input type="number" name="duration_days" value="{{ old('duration_days', $rental->duration_days) }}" min="1" class="form-input" required>
                </div>
        </div>

        <!-- Produk -->
        <div class="card p-6">
            <h3 class="font-playfair font-semibold text-base mb-5" style="color: var(--text-dark)">
                <i data-lucide="package" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Produk
            </h3>
            <div id="products-container" class="space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="flex gap-3 items-center flex-wrap">
                        <select x-model="item.product_id" :name="`items[${index}][product_id]`" class="form-input flex-1" :class="{ 'is-error': itemsErrors[index]?.product_id || itemsErrors[index]?.stock_exceeded }" @change="onProductChange(index)" :disabled="formLocked" title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}">
                            <option value="">Pilih Produk</option>
                            @foreach ($products as $product)
                                @php
                                    $isOut = (int)($product->stock_available ?? 0) <= 0;
                                    $fallbackSizes = $product->size ? [$product->size => ['stock_total' => $product->stock_total, 'stock_available' => $product->stock_available]] : [];
                                    $sizesData = $product->sizesData ?: $fallbackSizes;
                                @endphp
                                <option
                                    value="{{ $product->id }}"
                                    data-price="{{ $product->rental_price }}"
                                    data-deposit-price="{{ $product->deposit_price }}"
                                    data-stock="{{ $product->stock_available }}"
                                    data-sizes='@json($sizesData)'
                                    :selected="String(item.product_id) === String({{ $product->id }})"
                                    {{ $isOut ? 'disabled' : '' }}
                                >
                                    {{ $product->name }} (Stok: {{ $product->stock_available }}){{ $isOut ? ' (Habis)' : '' }}
                                    - Rp {{ number_format($product->rental_price, 0, ',', '.') }}/hari
                                </option>
                            @endforeach
                        </select>

                        <div class="flex items-center gap-1">
                            <select x-model="item.product_size" :name="`items[${index}][product_size]`" class="form-input w-32" :class="{ 'is-error': itemsErrors[index]?.product_size }" @change="onSizeChange(index)">
                                <option value="">Pilih Ukuran</option>
                                <template x-for="sz in sizesForItem(index)" :key="sz">
                                    <option :value="sz" x-text="sz"></option>
                                </template>
                            </select>
                            <span class="text-xs text-slate-500" x-show="item.product_size" x-text="'Stok: ' + getStockForProductAndSize(item.product_id, item.product_size)"></span>
                        </div>

                        <input type="number" x-model="item.quantity" :name="`items[${index}][quantity]`" value="1" min="1" class="form-input w-24" :max="item.product_size ? getStockForProductAndSize(item.product_id, item.product_size) : ''" :class="{ 'is-error': itemsErrors[index]?.quantity || itemsErrors[index]?.stock_exceeded }" @input="onQtyInput(index)" :disabled="formLocked" title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}">
                        <p x-show="itemsErrors[index]?.stock_exceeded" x-transition class="text-xs text-red-400 mt-1" x-text="itemsErrors[index]?.stock_message || 'Qty melebihi stok tersedia'"></p>

                        <button type="button" x-show="items.length > 1 && !formLocked" @click="removeItem(index)" class="btn-danger px-3 py-2" :disabled="formLocked" title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addItem()" class="btn-secondary mt-3" :disabled="formLocked" title="{{ $isFormLocked ? 'Tidak dapat diubah karena penyewaan sudah dibayar/berjalan' : '' }}">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Tambah Produk
            </button>
        </div>

        <!-- Jaminan & Pembayaran -->
        <div class="card p-6">
            <h3 class="font-playfair font-semibold text-base mb-5" style="color: var(--text-dark)">
                <i data-lucide="file-text" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Jaminan & Pembayaran
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Metode Pembayaran</label>
                    <select name="payment_method" class="form-input">
                        <option value="">Pilih Metode</option>
                        <option value="cash" {{ (string)($rental->payment_method ?? '') === 'cash' ? 'selected' : '' }}>Tunai</option>
                        @if($qris['available'])
                        <option value="qris" {{ (string)($rental->payment_method ?? '') === 'qris' ? 'selected' : '' }}>QRIS</option>
                        @endif
                        <option value="transfer" {{ (string)($rental->payment_method ?? '') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                    </select>
                </div>

                @php
                    $guarantee = $rental->guarantees->first();
                @endphp

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Jenis Jaminan</label>
                    <select name="guarantee_type" x-model="guaranteeType" class="form-input">
                        <option value="">Pilih Jaminan</option>
                        <option value="ktp" {{ $guarantee && $guarantee->type === 'ktp' ? 'selected' : '' }}>KTP</option>
                        <option value="sim" {{ $guarantee && $guarantee->type === 'sim' ? 'selected' : '' }}>SIM</option>
                        <option value="deposit" {{ $guarantee && $guarantee->type === 'deposit' ? 'selected' : '' }}>Deposit Uang</option>
                        <option value="custom" {{ $guarantee && $guarantee->type === 'custom' ? 'selected' : '' }}>Jaminan Custom</option>
                    </select>

                    <div x-show="guaranteeType === 'deposit'" x-transition class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Jumlah Deposit</label>
                        <input type="text" name="guarantee_deposit" x-model="guaranteeDeposit" placeholder="Masukkan jumlah deposit" value="{{ old('guarantee_deposit', $guarantee?->deposit_amount ?? '') }}" class="form-input">
                    </div>
                </div>

                @if($guarantee && $guarantee->type === 'custom')
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Deskripsi Jaminan</label>
                    <textarea name="guarantee_notes" class="form-input" rows="2" placeholder="Contoh: Motor Vario plat AD 1234 XX">{{ old('guarantee_notes', $guarantee->description ?? '') }}</textarea>
                    @error('guarantee_notes')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                @endif

                @if($guarantee && ($guarantee->type === 'ktp' || $guarantee->type === 'sim'))
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Upload Foto {{ $guarantee->type === 'ktp' ? 'KTP' : 'SIM' }}</label>

                     @if($guarantee && $guarantee->id_photo)
                        <div class="mb-3">
                            <p class="text-xs text-slate-500 mb-2">Foto saat ini:</p>
                            <img src="{{ $guarantee->id_photo_url }}" alt="Foto Identitas" class="h-40 w-auto rounded-lg border border-slate-200 object-cover cursor-pointer" @click="showPreview = !showPreview">
                            <template x-if="showPreview">
                                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click="showPreview = false">
                                    <img src="{{ $guarantee->id_photo_url }}" alt="Preview" class="max-h-[80vh] max-w-[90vw] rounded-xl shadow-2xl">
                                </div>
                            </template>
                            <label class="inline-flex items-center gap-2 mt-2 text-sm text-red-600 cursor-pointer">
                                <input type="checkbox" name="remove_guarantee_photo" value="1">
                                <span>Hapus foto</span>
                            </label>
                        </div>
                    @endif

                    <!-- Drop Zone -->
                    <div
                        x-ref="dropzone"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                        :class="{
                            'border-blue-400 bg-blue-50/50': dragOver,
                            'border-green-400 bg-green-50/30': photoState.verified && !dragOver,
                            'border-red-300 bg-red-50/30': photoState.checked && !photoState.verified && !dragOver,
                            'border-dashed border-slate-300 bg-slate-50/50': !photoState.file && !dragOver
                        }"
                        class="relative flex flex-col items-center justify-center w-full h-48 rounded-xl border-2 cursor-pointer transition-all duration-200"
                    >
                        <template x-if="!photoState.file">
                            <div class="flex flex-col items-center gap-2 text-slate-400">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-sm font-medium">Seret &amp; lepas foto di sini</p>
                                <p class="text-xs">atau klik untuk memilih file</p>
                                <p x-show="photoState.compressing" class="text-xs font-semibold text-blue-500">Mengompres foto…</p>
                                <p x-show="!photoState.compressing" class="text-xs text-slate-300">JPG/PNG, maks 2MB — foto besar otomatis dikompres</p>
                            </div>
                        </template>
                        <template x-if="photoState.file">
                            <div class="relative w-full h-full p-2">
                                <img :src="photoState.previewUrl" class="w-full h-full object-contain rounded-lg" alt="Preview">
                                <button type="button" @click.stop="removePhoto()" class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-white/90 shadow-md text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                <button type="button" @click.stop="$refs.fileInput.click()" class="absolute bottom-3 right-3 px-3 py-1.5 text-xs font-medium rounded-lg bg-white/90 shadow-md text-blue-600 hover:bg-blue-50 transition-colors">
                                    Ganti
                                </button>
                            </div>
                        </template>

                        <input type="file" x-ref="fileInput" name="guarantee_id_photo" accept="image/jpeg,image/png" @change="handleFileSelect($event)" class="hidden">
                    </div>
                    @error('guarantee_id_photo')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror

                    <!-- Quality Check Status -->
                    <div x-show="photoState.checked" class="mt-3 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs" :class="photoState.resolutionOk ? 'text-green-600' : 'text-red-500'">
                            <template x-if="photoState.resolutionOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.resolutionOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.resolutionOk ? '✓ Resolusi cukup' : '✗ Resolusi terlalu kecil (min. 1280x720)'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs" :class="photoState.blurOk ? 'text-green-600' : 'text-red-500'">
                            <template x-if="photoState.blurOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.blurOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.blurOk ? '✓ Tidak blur' : '✗ Foto terlalu blur'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs" :class="photoState.brightnessOk ? 'text-green-600' : 'text-red-500'">
                            <template x-if="photoState.brightnessOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.brightnessOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.brightnessOk ? '✓ Pencahayaan baik' : '✗ Foto terlalu gelap'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs" :class="photoState.verified ? 'text-green-600' : 'text-amber-600'">
                            <template x-if="photoState.verified">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.verified">
                                <svg class="w-4 h-4 shrink-0 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.verified ? '✓ Siap diupload' : 'Perbaiki kualitas foto sebelum upload'"></span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Format: JPG/PNG. Maks 2MB. Biarkan kosong jika tidak ingin mengubah.</p>
                </div>
                @endif
            </div>

        <!-- Diskon & Status & Catatan (existing) -->
        <div class="card p-6">
            <h3 class="font-playfair font-semibold text-base mb-5" style="color: var(--text-dark)">
                <i data-lucide="file-text" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Diskon & Status & Catatan
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Diskon (Rp)</label>
                    <input type="number" name="discount" value="{{ old('discount', $rental->discount) }}" min="0" class="form-input">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Status</label>
                    <select name="rental_status" class="form-input">
                        @foreach(['waiting'=>'Menunggu Pembayaran','active'=>'Disewa','overdue'=>'Terlambat','returned'=>'Dikembalikan','cancelled'=>'Dibatalkan'] as $key=>$label)
                            <option value="{{ $key }}" {{ (string)$rental->rental_status === (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Payment Status</label>
                    <select name="payment_status" class="form-input">
                        @foreach(['unpaid'=>'Belum Bayar','partial'=>'Sebagian','paid'=>'Lunas'] as $key=>$label)
                            <option value="{{ $key }}" {{ (string)$rental->payment_status === (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Deposit</label>
                    <input type="number" name="deposit" value="{{ old('deposit', $rental->deposit ?? 0) }}" min="0" class="form-input">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Catatan</label>
                    <textarea name="notes" class="form-input" rows="3">{{ old('notes', $rental->notes) }}</textarea>
                </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-between">
            <a href="{{ route('rentals.show', $rental) }}" class="btn-secondary">
                <i data-lucide="x" class="w-4 h-4"></i>
                Batal
            </a>
            <button type="submit"
                    :disabled="submitting || (photoState.file && !photoState.verified)"
                    :class="{
                        'opacity-60 pointer-events-none': submitting || (photoState.file && !photoState.verified),
                    }"
                    class="btn-primary px-8">
                <span x-show="!submitting" class="flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Perubahan
                </span>
                <span x-show="submitting" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Menyimpan...
                </span>
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
 function rentalForm(rentalId, formLocked = false) {
     return {
         formLocked: formLocked,
         submitting: false,
        items: [
            @php
                $rentalItems = $rental->items;
            @endphp
            @foreach($rentalItems as $rentalItem)
            { product_id: {{ $rentalItem->product_id ?? 'null' }}, product_size: '{{ $rentalItem->product_size }}', quantity: {{ (int)$rentalItem->quantity }} },
            @endforeach
        ],
         itemsErrors: [],
        productSizesMap: @json($products->mapWithKeys(fn($p) => [$p->id => ($p->sizesData ?: ($p->size ? [$p->size => ['stock_total' => $p->stock_total, 'stock_available' => $p->stock_available]] : []))])->toArray()),
        dragOver: false,
        photoState: {
            file: null,
            previewUrl: null,
            compressing: false,
            checked: false,
            verified: false,
            resolutionOk: false,
            blurOk: false,
            brightnessOk: false,
        },
        LAPLACIAN_THRESHOLD: 100,

        customerSearch: @json($rental->customer->name . ' - ' . $rental->customer->phone),
        selectedCustomer: @json(['id' => $rental->customer_id, 'name' => $rental->customer->name, 'phone' => $rental->customer->phone]),
        customerDropdownOpen: false,
        showAddModal: false,
        showPreview: false,
        addingCustomer: false,
        newCustomer: { name: '', phone: '' },
        customers: @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])),
        guaranteeType: '{{ $guarantee?->type ?? '' }}',
        guaranteeDeposit: '{{ $guarantee?->deposit_amount ?? '' }}',

        getStockForProduct(productId) {
            if (!productId) return 0;
            const option = Array.from(document.querySelectorAll('#products-container select[name^="items"][name$="[product_id]"] option'))
                .find(opt => opt.value === String(productId));
            return option ? parseInt(option.dataset.stock || '0', 10) : 0;
        },

        getStockForProductAndSize(productId, size) {
            const sizes = this.productSizesMap[productId];
            if (!sizes || !sizes[size]) return 0;
            return parseInt(sizes[size].stock_available, 10);
        },

        sizesForItem(index) {
            const productId = this.items[index]?.product_id;
            if (!productId) return [];
            const sizes = this.productSizesMap[productId];
            if (!sizes) return [];
            const order = ['XS','S','M','L','XL','XXL','3XL','4XL','5XL','6XL','7XL'];
            return Object.keys(sizes).sort((a, b) => order.indexOf(a) - order.indexOf(b));
        },

        onProductChange(index) {
            const item = this.items[index];
            item.product_size = '';

            if (!this.itemsErrors[index]) {
                this.itemsErrors[index] = {};
            }
            this.itemsErrors[index].product_id = false;
            this.itemsErrors[index].product_size = false;
            this.itemsErrors[index].stock_exceeded = false;
            this.itemsErrors[index].stock_message = '';
        },

        onSizeChange(index) {
            const item = this.items[index];
            if (item.product_id && item.product_size) {
                const stock = this.getStockForProductAndSize(item.product_id, item.product_size);
                if (item.quantity > stock) {
                    item.quantity = stock;
                }
                if (!this.itemsErrors[index]) {
                    this.itemsErrors[index] = {};
                }
                this.itemsErrors[index].stock_exceeded = false;
                this.itemsErrors[index].stock_message = '';
            }
        },

        onQtyInput(index) {
            const item = this.items[index];
            const stock = item.product_id && item.product_size
                ? this.getStockForProductAndSize(item.product_id, item.product_size)
                : this.getStockForProduct(item.product_id);

            if (!this.itemsErrors[index]) {
                this.itemsErrors[index] = {};
            }

            if (item.product_id && stock > 0 && item.quantity > stock) {
                this.itemsErrors[index].stock_exceeded = true;
                this.itemsErrors[index].stock_message = `Qty melebihi stok tersedia (maks: ${stock})`;
            } else {
                this.itemsErrors[index].stock_exceeded = false;
                this.itemsErrors[index].stock_message = '';
            }
        },

        get filteredCustomers() {
            // Prefill "Nama - HP" pelanggan terpilih: saat dropdown dibuka tampilkan
            // daftar lengkap, bukan "tidak ada hasil" (kueri prefill tak cocok nama mentah)
            if (this.selectedCustomer
                && this.customerSearch === this.selectedCustomer.name + ' - ' + this.selectedCustomer.phone) {
                return this.customers;
            }
            if (!this.customerSearch) return this.customers;
            const q = this.customerSearch.toLowerCase();
            return this.customers.filter(c =>
                c.name.toLowerCase().includes(q) || c.phone.includes(q)
            );
        },

        get dropdownCustomers() {
            return this.filteredCustomers.slice(0, 50);
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerSearch = customer.name + ' - ' + customer.phone;
            this.customerDropdownOpen = false;
        },

        openAddModal() {
            this.newCustomer.name = this.customerSearch;
            this.newCustomer.phone = '';
            this.showAddModal = true;
        },

        closeAddModal() {
            this.showAddModal = false;
        },

        async addCustomer() {
            this.addingCustomer = true;
            try {
                const response = await fetch('{{ route('customers.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(this.newCustomer),
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    if (errorData.errors) {
                        const messages = Object.values(errorData.errors).flat().join('\n');
                        throw new Error(messages);
                    }
                    throw new Error(errorData.message || 'Gagal menyimpan customer');
                }

                const data = await response.json();
                this.selectedCustomer = data.customer;
                this.customerSearch = data.customer.name + ' - ' + data.customer.phone;
                this.showAddModal = false;
                this.customerDropdownOpen = false;

                this.customers.push(data.customer);
            } catch (error) {
                alert(error.message);
            } finally {
                this.addingCustomer = false;
            }
        },

        filterPhoneInput(event) {
            const input = event.target;
            let digits = input.value.replace(/[^0-9]/g, '');
            if (digits.startsWith('0')) {
                digits = '62' + digits.slice(1);
            }
            input.value = digits;
        },

        preventNonNumeric(event) {
            if ([8, 46, 9, 27, 13].includes(event.keyCode) ||
                (event.keyCode === 65 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode === 67 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode === 86 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode === 88 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode >= 35 && event.keyCode <= 39)) {
                return;
            }
            if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) && (event.keyCode < 96 || event.keyCode > 105)) {
                event.preventDefault();
            }
        },

        addItem() {
            this.items.push({ product_id: null, product_size: '', quantity: 1 });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        submitForm() {
            this.itemsErrors = this.items.map((item) => {
                const errors = {
                    product_id: !item.product_id,
                    product_size: !item.product_size,
                    quantity: !item.quantity || item.quantity < 1,
                    stock_exceeded: false,
                    stock_message: ''
                };

                if (item.product_id && item.product_size) {
                    const stock = this.getStockForProductAndSize(item.product_id, item.product_size);
                    if (stock > 0 && item.quantity > stock) {
                        errors.stock_exceeded = true;
                        errors.stock_message = `Qty melebihi stok tersedia (maks: ${stock})`;
                    }
                } else if (item.product_id) {
                    const stock = this.getStockForProduct(item.product_id);
                    if (stock > 0 && item.quantity > stock) {
                        errors.stock_exceeded = true;
                        errors.stock_message = `Qty melebihi stok tersedia (maks: ${stock})`;
                    }
                }

                return errors;
            });

            const hasStockError = this.itemsErrors.some(err => err.stock_exceeded);
            if (hasStockError) {
                this.$nextTick(() => {
                    const firstError = document.querySelector('.is-error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                return;
            }

            this.submitting = true;
            this.$el.submit();
        },

        handleDrop(event) {
            this.dragOver = false;
            const file = event.dataTransfer.files[0];
            if (file) this.processFile(file);
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.processFile(file);
        },

        async processFile(file) {
            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                alert('Format tidak didukung. Gunakan JPG atau PNG.');
                return;
            }

            // Kompresi di browser: foto HP 4-8MB di-resize/mampatkan dulu supaya
            // aman melewati limit upload 2MB tanpa menolak file (lihat compressedUpload.js).
            this.photoState.compressing = true;
            try {
                file = await window.compressImageFile(file);
            } catch (e) {
                console.error('Kompresi gambar gagal, memakai file asli:', e);
            } finally {
                this.photoState.compressing = false;
            }

            // Guard terakhir (jarang terjadi setelah kompresi)
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file masih terlalu besar setelah kompresi. Coba foto ulang.');
                return;
            }

            // Sinkron ke <input type="file"> supaya file ikut terkirim saat submit,
            // termasuk jalur drag & drop yang sebelumnya tidak terkirim.
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;

            const reader = new FileReader();
            reader.onload = (e) => {
                this.photoState.previewUrl = e.target.result;
                this.photoState.file = file;
                this.photoState.checked = false;
                this.photoState.verified = false;
                this.runQualityChecks();
            };
            reader.readAsDataURL(file);
        },

        removePhoto() {
            this.photoState.file = null;
            this.photoState.previewUrl = null;
            this.photoState.checked = false;
            this.photoState.verified = false;
            this.photoState.resolutionOk = false;
            this.photoState.blurOk = false;
            this.photoState.brightnessOk = false;
            this.$refs.fileInput.value = '';
            // Also uncheck remove checkbox if exists
            const removeCheckbox = document.querySelector('input[name="remove_guarantee_photo"]');
            if (removeCheckbox) removeCheckbox.checked = false;
        },

        runQualityChecks() {
            const img = new Image();
            img.onload = () => {
                const minW = 1280, minH = 720;
                this.photoState.resolutionOk = img.naturalWidth >= minW && img.naturalHeight >= minH;

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const scale = Math.min(200 / img.naturalWidth, 200 / img.naturalHeight);
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;

                // Brightness check
                let totalBrightness = 0;
                const pixelCount = data.length / 4;
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i], g = data[i+1], b = data[i+2];
                    totalBrightness += 0.299 * r + 0.587 * g + 0.114 * b;
                }
                const avgBrightness = totalBrightness / pixelCount;
                this.photoState.brightnessOk = avgBrightness >= 30 && avgBrightness <= 240;

                // Laplacian Variance (blur detection)
                let sum = 0, sumSq = 0, count = 0;
                for (let y = 1; y < canvas.height - 1; y++) {
                    for (let x = 1; x < canvas.width - 1; x++) {
                        const idx = (y * canvas.width + x) * 4;
                        const center = 0.299 * data[idx] + 0.587 * data[idx+1] + 0.114 * data[idx+2];
                        const top = 0.299 * data[((y-1) * canvas.width + x) * 4] + 0.587 * data[((y-1) * canvas.width + x) * 4 + 1] + 0.114 * data[((y-1) * canvas.width + x) * 4 + 2];
                        const bottom = 0.299 * data[((y+1) * canvas.width + x) * 4] + 0.587 * data[((y+1) * canvas.width + x) * 4 + 1] + 0.114 * data[((y+1) * canvas.width + x) * 4 + 2];
                        const left = 0.299 * data[(y * canvas.width + (x-1)) * 4] + 0.587 * data[(y * canvas.width + (x-1)) * 4 + 1] + 0.114 * data[(y * canvas.width + (x-1)) * 4 + 2];
                        const right = 0.299 * data[(y * canvas.width + (x+1)) * 4] + 0.587 * data[(y * canvas.width + (x+1)) * 4 + 1] + 0.114 * data[(y * canvas.width + (x+1)) * 4 + 2];

                        const laplacian = Math.abs(4 * center - top - bottom - left - right);
                        sum += laplacian;
                        sumSq += laplacian * laplacian;
                        count++;
                    }
                }
                const variance = (sumSq / count) - (sum / count) * (sum / count);
                this.photoState.blurOk = variance >= this.LAPLACIAN_THRESHOLD;

                this.photoState.checked = true;
                this.photoState.verified = this.photoState.resolutionOk && this.photoState.blurOk && this.photoState.brightnessOk;
            };
            img.src = this.photoState.previewUrl;
        },

        handleRemovePhoto(event) {
            if (event.target.checked) {
                this.removePhoto();
            }
        }
    }
}
</script>
@endpush

