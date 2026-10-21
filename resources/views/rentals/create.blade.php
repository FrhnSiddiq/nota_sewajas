@extends('Layouts.app')

@section('title', 'Buat Penyewaan')
@section('page-title', 'Buat Penyewaan')

@section('content')
<div class="mx-auto w-full max-w-4xl space-y-5">
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('rentals.index') }}" class="btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali ke Penyewaan
        </a>
    </div>
    <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data" x-data="rentalForm()" @submit.prevent="submitForm()" novalidate class="space-y-5">
        @csrf

        <!-- Customer & Rental Info -->
        <div class="card p-4 sm:p-6">
            <h3 class="font-playfair font-semibold text-base mb-4 sm:mb-5" style="color: var(--text-dark)">
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
                            @input="selectedCustomer = null; customerError = false; customerDropdownOpen = true"
                            :class="{ 'is-error': customerError }"
                            class="form-input"
                            placeholder="Pilih pelanggan atau ketik untuk mencari..."
                            autocomplete="off"
                        >
                        <input type="hidden" name="customer_id" :value="selectedCustomer?.id ?? ''">

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
                    @error('customer_id')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    <p x-show="customerError" x-transition class="text-xs text-red-400 mt-1">Pelanggan wajib dipilih</p>
                </div>

                <!-- Modal Tambah Pelanggan Baru -->
                <div x-show="showAddModal" x-transition class="fixed inset-0 z-[100] flex items-center justify-center" style="display: none;">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showAddModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-auto" @click.stop>
                        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-slate-100">
                            <h3 class="font-semibold text-base text-slate-800">Tambah Pelanggan Baru</h3>
                            <button type="button" @click="showAddModal = false" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="p-4 sm:p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1.5 text-slate-700">Nama Lengkap <span class="text-red-400">*</span></label>
                                <input type="text" x-model="newCustomer.name" class="form-input" @input="newCustomerNameError = false">
                                <p x-show="newCustomerNameError" x-transition class="text-xs text-red-400 mt-1">Nama lengkap wajib diisi</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5 text-slate-700">Nomor Handphone <span class="text-red-400">*</span></label>
                                <div class="input-group-phone">
                                    <span class="input-group-phone__prefix">+62</span>
                                    <input type="text"
                                        x-model="newCustomer.phone"
                                        class="input-group-phone__input"
                                        placeholder="8123456789"
                                        inputmode="numeric"
                                        @input="filterPhoneInput($event); newCustomerPhoneError = false"
                                        @keydown="preventNonNumeric($event)"
                                        autocomplete="tel"
                                    >
                                </div>
                                <p x-show="newCustomerPhoneError" x-transition class="text-xs text-red-400 mt-1">Nomor handphone wajib diisi</p>
                            </div>
                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button type="button" @click="showAddModal = false" class="btn-secondary px-5 py-2.5" :disabled="addingCustomer">Batal</button>
                                <button type="button" @click="addCustomer()" class="btn-primary px-5 py-2.5" :disabled="addingCustomer">
                                    <span x-show="!addingCustomer">Simpan Pelanggan</span>
                                    <span x-show="addingCustomer" class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Menyimpan...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Tanggal Sewa <span class="text-red-400">*</span></label>
                    <input type="date" name="rental_date" value="{{ old('rental_date', now()->format('Y-m-d')) }}" class="form-input" @input="rentalDateError = false">
                    @error('rental_date')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    <p x-show="rentalDateError" x-transition class="text-xs text-red-400 mt-1">Tanggal sewa wajib diisi</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Durasi (Hari) <span class="text-red-400">*</span></label>
                    <input type="number" name="duration_days" value="{{ old('duration_days', 1) }}" min="1" class="form-input" @input="durationDaysError = false">
                    @error('duration_days')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    <p x-show="durationDaysError" x-transition class="text-xs text-red-400 mt-1">Durasi hari wajib diisi</p>
                </div>
            </div>
        </div>

        <!-- Produk -->
        <div class="card p-4 sm:p-6">
            <h3 class="font-playfair font-semibold text-base mb-4 sm:mb-5" style="color: var(--text-dark)">
                <i data-lucide="package" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Produk
            </h3>
            <div id="products-container" class="space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="grid items-start gap-3 sm:grid-cols-[minmax(0,1fr)_8rem_6rem_2.75rem]" :class="items.length > 1 ? 'grid-cols-[minmax(0,1fr)_minmax(0,1fr)_2.75rem]' : 'grid-cols-2'">
                        <div class="min-w-0 sm:col-span-1" :class="items.length > 1 ? 'col-span-3 sm:col-span-1' : 'col-span-2 sm:col-span-1'">
                            <select x-model="item.product_id" :name="`items[${index}][product_id]`" class="form-input min-w-0 w-full" :class="{ 'is-error': itemsErrors[index]?.product_id || itemsErrors[index]?.stock_exceeded }" @change="onProductChange(index)">
                                <option value="">Pilih Produk</option>
                                @foreach ($products as $product)
                                    @php
                                        $fallbackSizes = $product->size ? [$product->size => ['stock_total' => $product->stock_total, 'stock_available' => $product->stock_available]] : [];
                                        $sizesData = $product->sizesData ?: $fallbackSizes;
                                    @endphp
                                    <option value="{{ $product->id }}" data-price="{{ $product->rental_price }}" data-deposit-price="{{ $product->deposit_price }}" data-stock="{{ $product->stock_available }}" data-sizes='@json($sizesData)'>{{ $product->name }} (Stok: {{ $product->stock_available }}) - Rp {{ number_format($product->rental_price, 0, ',', '.') }}/hari</option>
                                @endforeach
                            </select>
                            <p x-show="itemsErrors[index]?.product_id" x-transition class="mt-1 text-xs text-red-400">Pilih produk</p>
                            <p x-show="itemsErrors[index]?.stock_exceeded" x-transition class="mt-1 text-xs text-red-400" x-text="itemsErrors[index]?.stock_message || 'Qty melebihi stok tersedia'"></p>
                        </div>
                        <div class="min-w-0">
                            <select x-model="item.product_size" :name="`items[${index}][product_size]`" class="form-input min-w-0 w-full sm:w-32" :class="{ 'is-error': itemsErrors[index]?.product_size }" @change="onSizeChange(index)">
                                <option value="">Ukuran</option>
                                <template x-for="sz in sizesForItem(index)" :key="sz">
                                    <option :value="sz" x-text="sz"></option>
                                </template>
                            </select>
                            <span class="mt-1 block text-xs text-slate-500" x-show="item.product_size" x-text="'Stok: ' + getStockForProductAndSize(item.product_id, item.product_size)"></span>
                            <p x-show="itemsErrors[index]?.product_size" x-transition class="mt-1 text-xs text-red-400">Pilih ukuran</p>
                        </div>
                        <div class="min-w-0">
                            <input type="number" x-model="item.quantity" :name="`items[${index}][quantity]`" value="1" min="1" class="form-input min-w-0 w-full sm:w-24" :max="item.product_size ? getStockForProductAndSize(item.product_id, item.product_size) : ''" :class="{ 'is-error': itemsErrors[index]?.quantity || itemsErrors[index]?.stock_exceeded }" @input="onQtyInput(index)">
                            <p x-show="itemsErrors[index]?.quantity" x-transition class="mt-1 text-xs text-red-400">Jumlah minimal 1</p>
                        </div>
                        <button type="button" x-show="items.length > 1" @click="removeItem(index)" class="btn-danger flex h-11 w-11 items-center justify-center px-3 py-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </template>
            </div>
            <p x-show="productsError" x-transition class="text-xs text-red-400 mt-2">Lengkapi semua data produk yang ditambahkan</p>
            <button type="button" @click="addItem()" class="btn-secondary mt-3">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Tambah Produk
            </button>
        </div>

        <!-- Diskon & Catatan -->
        <div class="card p-4 sm:p-6">
            <h3 class="font-playfair font-semibold text-base mb-4 sm:mb-5" style="color: var(--text-dark)">
                <i data-lucide="file-text" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Diskon & Catatan
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Diskon (Rp)</label>
                    <input type="number" name="discount" value="{{ old('discount', 0) }}" min="0" class="form-input">
                    @error('discount')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Catatan</label>
                    <textarea name="notes" class="form-input" rows="1">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- Jaminan & Pembayaran -->
        <div class="card p-4 sm:p-6">
            <h3 class="font-playfair font-semibold text-base mb-4 sm:mb-5" style="color: var(--text-dark)">
                <i data-lucide="shield" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Jaminan & Pembayaran
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Metode Pembayaran <span class="text-red-400">*</span></label>
                    <select name="payment_method" class="form-input" @change="paymentMethodError = false" x-model="paymentMethod">
                        <option value="">Pilih Metode</option>
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        @if($qris['available'])
                        <option value="qris" {{ old('payment_method') === 'qris' ? 'selected' : '' }}>QRIS</option>
                        @endif
                        <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                    </select>
                    @error('payment_method')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    <p x-show="paymentMethodError" x-transition class="text-xs text-red-400 mt-1">Metode pembayaran wajib dipilih</p>
                    @if($qris['available'])
                    <div x-show="paymentMethod === 'qris'" x-transition class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-center">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Scan QR code di bawah untuk membayar via QRIS</p>
                        <img src="{{ $qris['url'] }}" alt="QRIS Payment" class="mx-auto h-48 w-48 rounded-xl border border-slate-100 object-contain">
                    </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Jenis Jaminan <span class="text-red-400">*</span></label>
                    <select name="guarantee_type" class="form-input" @change="guaranteeTypeError = false" x-model="guaranteeType">
                        <option value="">Pilih Jenis Jaminan</option>
                        <option value="ktp" {{ old('guarantee_type') === 'ktp' ? 'selected' : '' }}>KTP</option>
                        <option value="sim" {{ old('guarantee_type') === 'sim' ? 'selected' : '' }}>SIM</option>
                        <option value="deposit" {{ old('guarantee_type') === 'deposit' ? 'selected' : '' }}>Deposit Uang</option>
                        <option value="custom" {{ old('guarantee_type') === 'custom' ? 'selected' : '' }}>Jaminan Custom</option>
                    </select>
                    @error('guarantee_type')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    <p x-show="guaranteeTypeError" x-transition class="text-xs text-red-400 mt-1">Jenis jaminan wajib dipilih</p>

                    <div x-show="guaranteeType === 'deposit'" x-transition class="mt-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Jumlah Deposit <span class="text-red-400">*</span></label>
                        <input type="text" name="guarantee_deposit" x-model="guaranteeDeposit" @input="onDepositInput()" placeholder="Masukkan jumlah deposit" class="form-input">
                        <p class="text-xs text-slate-500 mt-1">Saran minimum: Rp <span x-text="suggestedDeposit"></span></p>
                        <p x-show="guaranteeDepositError" x-transition class="text-xs text-red-400 mt-1">Jumlah deposit wajib diisi</p>
                    </div>

                    <div x-show="guaranteeType === 'custom'" x-transition class="mt-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Deskripsi Jaminan <span class="text-red-400">*</span></label>
                        <textarea name="guarantee_notes" rows="2" placeholder="Contoh: Motor Vario plat AD 1234 XX" class="form-input">{{ old('guarantee_notes') }}</textarea>
                        @error('guarantee_notes')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <!-- Upload Foto Identitas -->
                <div class="sm:col-span-2" x-show="guaranteeType === 'ktp' || guaranteeType === 'sim'" x-transition>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">
                        Upload Foto <span x-text="guaranteeType === 'ktp' ? 'KTP' : 'SIM'"></span> <span class="text-red-400">*</span>
                    </label>

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
                    <p x-show="photoError" x-transition class="text-xs text-red-400 mt-1">Foto KTP/SIM wajib diupload</p>

                    <!-- Quality Check Status -->
                    <div x-show="photoState.checked" class="mt-3 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs" :class="photoState.resolutionOk ? 'text-green-600' : 'text-red-500'">
                            <template x-if="photoState.resolutionOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.resolutionOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.resolutionOk ? '&#x2713; Resolusi cukup' : '&#x2717; Resolusi terlalu kecil (min. 1280x720)'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs" :class="photoState.blurOk ? 'text-green-600' : 'text-red-500'">
                            <template x-if="photoState.blurOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.blurOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.blurOk ? '&#x2713; Tidak blur' : '&#x2717; Foto terlalu blur'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs" :class="photoState.brightnessOk ? 'text-green-600' : 'text-red-500'">
                            <template x-if="photoState.brightnessOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.brightnessOk">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.brightnessOk ? '&#x2713; Pencahayaan baik' : '&#x2717; Foto terlalu gelap'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs" :class="photoState.verified ? 'text-green-600' : 'text-amber-600'">
                            <template x-if="photoState.verified">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="!photoState.verified">
                                <svg class="w-4 h-4 shrink-0 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            </template>
                            <span x-text="photoState.verified ? '&#x2713; Siap diupload' : 'Perbaiki kualitas foto sebelum upload'"></span>
                        </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Submit -->
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('rentals.index') }}" class="btn-secondary w-full sm:w-auto">
                <i data-lucide="x" class="w-4 h-4"></i>
                Batal
            </a>
            <button type="submit"
                    :disabled="submitting || (photoState.file && !photoState.verified)"
                    :class="{
                        'opacity-60 pointer-events-none': submitting || (photoState.file && !photoState.verified),
                    }"
                    class="btn-primary w-full px-8 sm:w-auto">
                <span x-show="!submitting" class="flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Penyewaan
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
@php
    $oldCustomerId = old('customer_id');
    $oldCustomer = $oldCustomerId ? $customers->firstWhere('id', $oldCustomerId) : null;
@endphp
<script>
    function rentalForm() {
        return {
            submitting: false,
            customerError: false,
            rentalDateError: false,
            durationDaysError: false,
            paymentMethodError: false,
            paymentMethod: '{{ old('payment_method', '') }}',
            guaranteeTypeError: false,
            guaranteeType: '{{ old('guarantee_type', '') }}',
            guaranteeDeposit: '{{ old('guarantee_deposit', '') }}',
            depositManualEdited: false,
            guaranteeDepositError: false,
            photoError: false,
            productsError: false,
            newCustomerNameError: false,
            newCustomerPhoneError: false,
            itemsErrors: [],
            productSizesMap: @json($products->mapWithKeys(fn($p) => [$p->id => ($p->sizesData ?: ($p->size ? [$p->size => ['stock_total' => $p->stock_total, 'stock_available' => $p->stock_available]] : []))])->toArray()),
            items: [
                { product_id: '', product_size: '', quantity: 1 }
            ],
            dragOver: false,
            stockExceeded: false,
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

        customerSearch: @json($oldCustomer ? $oldCustomer->name . ' - ' . $oldCustomer->phone : ''),
        selectedCustomer: @json($oldCustomer ? ['id' => $oldCustomer->id, 'name' => $oldCustomer->name, 'phone' => $oldCustomer->phone] : null),
        customerDropdownOpen: false,
        showAddModal: false,
        addingCustomer: false,
        newCustomer: { name: '', phone: '' },
        customers: @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])),

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

        get suggestedDeposit() {
            if (!this.items) return 0;
            const productIds = this.items.map(i => i.product_id).filter(Boolean);
            if (!productIds.length) return 0;
            const productOptions = Array.from(document.querySelectorAll('#products-container select[name^="items"][name$="[product_id]"] option'));
            let total = 0;
            productOptions.forEach(opt => {
                if (productIds.includes(opt.value)) {
                    total += parseFloat(opt.dataset.depositPrice || 0);
                }
            });
            return total;
        },

        onDepositInput() {
            this.depositManualEdited = true;
            this.guaranteeDepositError = false;
        },

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

            this.itemsErrors[index].quantity = !item.quantity || item.quantity < 1;
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerSearch = customer.name + ' - ' + customer.phone;
            this.customerError = false;
            this.customerDropdownOpen = false;
        },

        openAddModal() {
            this.newCustomer.name = this.customerSearch;
            this.newCustomer.phone = '';
            this.newCustomerNameError = false;
            this.newCustomerPhoneError = false;
            this.showAddModal = true;
        },

        closeAddModal() {
            this.showAddModal = false;
        },

        submitForm() {
            this.customerError = false;
            this.rentalDateError = false;
            this.durationDaysError = false;
            this.paymentMethodError = false;
            this.guaranteeTypeError = false;
            this.photoError = false;
            this.productsError = false;
            this.itemsErrors = this.items.map(() => ({ product_id: false, product_size: false, quantity: false }));

            let hasError = false;

            if (!this.selectedCustomer) {
                this.customerError = true;
                hasError = true;
            }

            const rentalDateInput = document.querySelector('input[name="rental_date"]');
            if (!rentalDateInput || !rentalDateInput.value) {
                this.rentalDateError = true;
                hasError = true;
            }

            const durationInput = document.querySelector('input[name="duration_days"]');
            if (!durationInput || !durationInput.value || parseInt(durationInput.value) < 1) {
                this.durationDaysError = true;
                hasError = true;
            }

            let itemsInvalid = false;
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
                        itemsInvalid = true;
                    }
                } else if (item.product_id) {
                    const stock = this.getStockForProduct(item.product_id);
                    if (stock > 0 && item.quantity > stock) {
                        errors.stock_exceeded = true;
                        errors.stock_message = `Qty melebihi stok tersedia (maks: ${stock})`;
                        itemsInvalid = true;
                    }
                }

                if (errors.product_id || errors.product_size || errors.quantity) {
                    itemsInvalid = true;
                }
                return errors;
            });
            if (itemsInvalid) {
                this.productsError = true;
                hasError = true;
            }

            const paymentInput = document.querySelector('select[name="payment_method"]');
            if (!paymentInput || !paymentInput.value) {
                this.paymentMethodError = true;
                hasError = true;
            }

            const guaranteeInput = document.querySelector('select[name="guarantee_type"]');
            if (!guaranteeInput || !guaranteeInput.value) {
                this.guaranteeTypeError = true;
                hasError = true;
            }

            if (this.guaranteeType === 'deposit') {
                const depositInput = document.querySelector('input[name="guarantee_deposit"]');
                if (!depositInput || !depositInput.value || parseFloat(depositInput.value.replace(/[^0-9]/g, '')) <= 0) {
                    this.guaranteeDepositError = true;
                    hasError = true;
                }
            }

            if (this.guaranteeType === 'ktp' || this.guaranteeType === 'sim') {
                if (!this.photoState.file) {
                    this.photoError = true;
                    hasError = true;
                }
            }

            if (this.guaranteeType === 'custom') {
                const notesInput = document.querySelector('textarea[name="guarantee_notes"]');
                if (!notesInput || !notesInput.value.trim()) {
                    this.guaranteeTypeError = true;
                    hasError = true;
                }
            }

            if (hasError) {
                this.$nextTick(() => {
                    const firstErrorInput = document.querySelector('input.is-error, select.is-error, .input-group-phone__input.is-error');
                    if (firstErrorInput) {
                        firstErrorInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                return;
            }

            this.submitting = true;
            this.$el.submit();
        },

        async addCustomer() {
            this.addingCustomer = true;
            this.newCustomerNameError = false;
            this.newCustomerPhoneError = false;

            if (!this.newCustomer.name.trim()) {
                this.newCustomerNameError = true;
                this.addingCustomer = false;
                return;
            }
            if (!this.newCustomer.phone.trim()) {
                this.newCustomerPhoneError = true;
                this.addingCustomer = false;
                return;
            }

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
            this.items.push({ product_id: '', product_size: '', quantity: 1 });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
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
            // Validate type
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
        },

        runQualityChecks() {
            const img = new Image();
            img.onload = () => {
                // 1. Resolution check (min 1280x720)
                const minW = 1280, minH = 720;
                this.photoState.resolutionOk = img.naturalWidth >= minW && img.naturalHeight >= minH;

                // 2. Aspect ratio check (document-like: ~1.4 to ~1.6 or portrait)
                const ratio = img.naturalWidth / img.naturalHeight;
                this.photoState.aspectOk = ratio >= 0.6 && ratio <= 1.8;

                // 3. Draw to canvas for pixel-level checks
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                // Use smaller canvas for performance
                const scale = Math.min(200 / img.naturalWidth, 200 / img.naturalHeight);
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;

                // 3a. Brightness check (average luminance)
                let totalBrightness = 0;
                const pixelCount = data.length / 4;
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i], g = data[i+1], b = data[i+2];
                    totalBrightness += 0.299 * r + 0.587 * g + 0.114 * b;
                }
                const avgBrightness = totalBrightness / pixelCount;

                // Too dark if average < 30, too bright if > 240
                this.photoState.brightnessOk = avgBrightness >= 30 && avgBrightness <= 240;

                // 3b. Laplacian Variance (blur detection)
                // Simplified: compute variance of pixel differences (approximate Laplacian)
                let sum = 0, sumSq = 0, count = 0;
                for (let y = 1; y < canvas.height - 1; y++) {
                    for (let x = 1; x < canvas.width - 1; x++) {
                        const idx = (y * canvas.width + x) * 4;
                        // Approximate Laplacian: sum of differences from neighbors
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

                // Threshold: variance < ~100 means very blurry (tunable)
                this.photoState.blurOk = variance >= this.LAPLACIAN_THRESHOLD;

                // 4. Overall verification
                this.photoState.checked = true;
                this.photoState.verified = this.photoState.resolutionOk && this.photoState.blurOk && this.photoState.brightnessOk;
            };
            img.src = this.photoState.previewUrl;
        },

        watch: {
            items: {
                handler() {
                    if (this.guaranteeType === 'deposit' && !this.depositManualEdited) {
                        this.guaranteeDeposit = this.suggestedDeposit;
                    }
                },
                deep: true
            },
            guaranteeType(newVal) {
                if (newVal === 'deposit') {
                    this.guaranteeDeposit = this.suggestedDeposit;
                    this.depositManualEdited = false;
                } else {
                    this.guaranteeDeposit = '';
                    this.depositManualEdited = false;
                }

                if (newVal === 'ktp' || newVal === 'sim') {
                    // keep photo state as is if already uploaded
                } else {
                    // reset photo state when switching away from KTP/SIM
                    this.photoState.file = null;
                    this.photoState.previewUrl = null;
                    this.photoState.checked = false;
                    this.photoState.verified = false;
                    this.photoState.resolutionOk = false;
                    this.photoState.blurOk = false;
                    this.photoState.brightnessOk = false;
                    this.photoError = false;
                }
            }
        }
    }
}
</script>
@endpush
