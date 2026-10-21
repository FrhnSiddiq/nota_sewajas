@extends('Layouts.app')

@section('title', 'Pengaturan')

@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">Pengaturan</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-soft)">Kelola QRIS, rekening, informasi perusahaan, dan branding aplikasi</p>
        </div>
    </div>

    {{-- FORM --}}
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- ─── QRIS PEMBAYARAN ─────────────────────────────── --}}
        <div class="ds-card p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                    <i data-lucide="qr-code" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                </div>
                <h2 class="font-semibold text-sm" style="color:var(--text-dark)">QRIS Pembayaran</h2>
            </div>

            @php
                $qrisPath = $settings['qris_image'] ?? null;
                $qrisUrl = '';
                if ($qrisPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($qrisPath)) {
                    $fullPath = storage_path('app/public/' . $qrisPath);
                    $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
                    $qrisUrl = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fullPath));
                }
            @endphp

            <div x-data="qrisUploadForm('{{ $qrisUrl }}')" class="space-y-3">
                <div x-show="photoPreview || hasCurrentPhoto" class="relative" style="max-width: 280px;">
                    <img :src="photoPreview || currentPhotoUrl" class="w-full rounded-xl border" style="border-color:var(--border)" alt="Preview QRIS">
                    <button type="button" @click="clearPhoto()"
                            class="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center"
                            style="background: rgba(0,0,0,0.5)">
                        <i data-lucide="x" class="w-3.5 h-3.5 text-white"></i>
                    </button>
                </div>

                <div x-show="!photoPreview && !hasCurrentPhoto"
                     class="border-2 border-dashed rounded-xl p-5 text-center cursor-pointer transition-colors"
                     style="border-color:var(--border); max-width: 280px;"
                     @click="$refs.qrisInput.click()"
                     @dragover.prevent="isDragging = true"
                     @dragenter.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="isDragging = false; handleDrop($event)"
                     :class="isDragging ? 'border-blue-500 bg-blue-50' : ''">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mx-auto mb-2" style="background:var(--secondary)">
                        <i data-lucide="upload-cloud" class="w-5 h-5" style="color:var(--primary)"></i>
                    </div>
                    <p class="text-sm font-medium" style="color:var(--text-dark)">Klik atau seret gambar QRIS</p>
                    <p class="text-xs mt-1" style="color:var(--text-soft)">PNG, JPG, WEBP — Maks. 2MB, foto besar otomatis dikompres</p>
                </div>

                <input type="file" x-ref="qrisInput" name="qris_image" accept="image/png,image/jpeg,image/webp" class="hidden"
                       @change="handlePhotoSelect($event)">

                {{-- Quality Check Checklist --}}
                <div x-show="checked" class="mt-3 space-y-1.5">
                    <div class="flex items-center gap-2 text-xs" :class="resolutionOk ? 'text-green-600' : 'text-red-500'">
                        <template x-if="resolutionOk">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </template>
                        <template x-if="!resolutionOk">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </template>
                        <span x-text="resolutionOk ? '✓ Resolusi cukup (min. 200x200)' : '✗ Resolusi terlalu kecil (min. 200x200)'"></span>
                    </div>
                    <div class="flex items-center gap-2 text-xs" :class="formatOk ? 'text-green-600' : 'text-red-500'">
                        <template x-if="formatOk">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </template>
                        <template x-if="!formatOk">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </template>
                        <span x-text="formatOk ? '✓ Format file didukung (PNG/JPG/WEBP)' : '✗ Format file tidak didukung'"></span>
                    </div>
                    <div class="flex items-center gap-2 text-xs" :class="verified ? 'text-green-600' : 'text-amber-600'">
                        <template x-if="verified">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </template>
                        <template x-if="!verified">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 10a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                        </template>
                        <span x-text="verified ? '✓ Siap diupload' : 'Perbaiki kualitas gambar sebelum upload'"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── INFORMASI REKENING TRANSFER ─────────────────── --}}
        <div class="ds-card p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                    <i data-lucide="landmark" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                </div>
                <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Informasi Rekening Transfer</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Nama Bank</label>
                    <input type="text" name="bank_name"
                           value="{{ old('bank_name', $settings['bank_name'] ?? null) }}"
                           class="form-input" placeholder="Contoh: BCA">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Nomor Rekening</label>
                    <input type="text" name="bank_account"
                           value="{{ old('bank_account', $settings['bank_account'] ?? null) }}"
                           class="form-input" placeholder="Contoh: 1234567890">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Atas Nama</label>
                    <input type="text" name="bank_holder"
                           value="{{ old('bank_holder', $settings['bank_holder'] ?? null) }}"
                           class="form-input" placeholder="Contoh: PT SewaJas Indonesia">
                </div>
            </div>
        </div>

        {{-- ─── INFORMASI PERUSAHAAN ─────────────────────────── --}}
        <div class="ds-card p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                    <i data-lucide="building" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                </div>
                <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Informasi Perusahaan</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Tagline</label>
                    <input type="text" name="company_tagline"
                           value="{{ old('company_tagline', $settings['company_tagline'] ?? null) }}"
                           class="form-input" placeholder="Contoh: Premium Suit Rental">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Alamat</label>
                <textarea name="company_address" rows="2" class="form-input resize-none"
                           placeholder="Alamat lengkap perusahaan">{{ old('company_address', $settings['company_address'] ?? null) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Telepon</label>
                    <input type="text" name="company_phone"
                           inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           value="{{ old('company_phone', $settings['company_phone'] ?? null) }}"
                           class="form-input" placeholder="Contoh: 021-12345678">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Website</label>
                    <input type="text" name="company_website"
                           value="{{ old('company_website', $settings['company_website'] ?? null) }}"
                           class="form-input" placeholder="Contoh: www.sewajas.id">
                </div>
            </div>
        </div>

        {{-- ─── LOGO & NAMA APLIKASI ─────────────────────────── --}}
        <div class="ds-card p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                    <i data-lucide="palette" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                </div>
                <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Logo & Nama Aplikasi</h2>
                <p class="text-xs" style="color:var(--text-soft)">(sidebar, login, dan invoice)</p>
            </div>

            <p class="text-xs" style="color:var(--text-soft)">
                Gunakan gambar rasio 1:1 (persegi), minimal 200×200px, background transparan (PNG) untuk hasil terbaik di semua tampilan (sidebar, invoice, halaman login).
            </p>

            @php
                $appLogoPath = $settings['app_logo'] ?? null;
                $appLogoUrl = '';
                if ($appLogoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($appLogoPath)) {
                    $fullPath = storage_path('app/public/' . $appLogoPath);
                    $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
                    $appLogoUrl = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fullPath));
                }
            @endphp

            <div x-data="imageUploadForm('{{ $appLogoUrl }}')" class="space-y-3">
                <div x-show="photoPreview || hasCurrentPhoto" class="relative" style="max-width: 120px;">
                    <img :src="photoPreview || currentPhotoUrl" class="w-full rounded-xl border" style="border-color:var(--border)" alt="Preview Logo Aplikasi">
                    <button type="button" @click="clearPhoto()"
                            class="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center"
                            style="background: rgba(0,0,0,0.5)">
                        <i data-lucide="x" class="w-3.5 h-3.5 text-white"></i>
                    </button>
                </div>

                <div x-show="!photoPreview && !hasCurrentPhoto"
                     class="border-2 border-dashed rounded-xl p-5 text-center cursor-pointer transition-colors"
                     style="border-color:var(--border); max-width: 280px;"
                     @click="$refs.appLogoInput.click()"
                     @dragover.prevent="isDragging = true"
                     @dragenter.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="isDragging = false; handleDrop($event)"
                     :class="isDragging ? 'border-blue-500 bg-blue-50' : ''">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mx-auto mb-2" style="background:var(--secondary)">
                        <i data-lucide="upload-cloud" class="w-5 h-5" style="color:var(--primary)"></i>
                    </div>
                    <p class="text-sm font-medium" style="color:var(--text-dark)">Klik atau seret logo aplikasi</p>
                    <p class="text-xs mt-1" style="color:var(--text-soft)">PNG, JPG, WEBP — Maks. 2MB, foto besar otomatis dikompres</p>
                </div>

                <input type="file" x-ref="appLogoInput" name="app_logo" accept="image/*" class="hidden"
                       @change="handlePhotoSelect($event)">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Nama Aplikasi</label>
                    <input type="text" name="app_name"
                           value="{{ old('app_name', $settings['app_name'] ?? null) }}"
                           class="form-input" placeholder="Contoh: SewaJas">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Tagline</label>
                    <input type="text" name="app_tagline"
                           value="{{ old('app_tagline', $settings['app_tagline'] ?? null) }}"
                           class="form-input" placeholder="Contoh: RENTAL JAS">
                </div>
            </div>
        </div>

        {{-- ─── PENGATURAN SEWA ──────────────────────────────── --}}
        <div class="ds-card p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                    <i data-lucide="settings" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                </div>
                <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Pengaturan Sewa</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Denda per Hari Telat (Rp)</label>
                    <input type="number" name="fine_per_day" min="0" step="1"
                           value="{{ old('fine_per_day', $settings['fine_per_day'] ?? 0) }}"
                           class="form-input" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Durasi Sewa Default (hari)</label>
                    <input type="number" name="rental_duration_days" min="0" step="1"
                           value="{{ old('rental_duration_days', $settings['rental_duration_days'] ?? 3) }}"
                           class="form-input" required>
                </div>
            </div>
        </div>

        {{-- ACTION BUTTONS --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('dashboard') }}" class="btn-secondary">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
            </a>
            <button type="submit" class="btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Semua
            </button>
        </div>

    </form>
</div>

@push('scripts')
<script>
function imageUploadForm(initialUrl) {
    return {
        photoPreview: null,
        hasCurrentPhoto: initialUrl ? true : false,
        currentPhotoUrl: initialUrl || '',
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
        // (Jalur drop dulu tidak pernah mengisi input sehingga gambar hasil
        // drop tidak terkirim — kini ikut terkirim seperti jalur pilih file.)
        async applyPhoto(file) {
            const inputEl = this.$refs.appLogoInput;
            const finalFile = window.compressAndAssign
                ? await window.compressAndAssign(inputEl, file)
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
            this.currentPhotoUrl = '';
            const refs = Object.keys(this.$refs);
            if (refs.length > 0) {
                this.$refs[refs[0]].value = '';
            }
        },
    };
}

function qrisUploadForm(initialUrl) {
    return {
        photoPreview: null,
        hasCurrentPhoto: initialUrl ? true : false,
        currentPhotoUrl: initialUrl || '',
        isDragging: false,
        checked: false,
        verified: false,
        resolutionOk: false,
        formatOk: false,

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

        // Cek format dari file ASLI dulu, baru kompres — quality check
        // (resolusi min 200x200) dijalankan pada artefak final yang akan dikirim.
        // Kompresi tidak pernah upscale, jadi aturan dimensions tetap aman.
        async applyPhoto(file) {
            const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
            this.formatOk = allowedTypes.includes(file.type);
            if (!this.formatOk) {
                this.checked = true;
                this.verified = false;
                return;
            }

            const finalFile = window.compressAndAssign
                ? await window.compressAndAssign(this.$refs.qrisInput, file)
                : file;

            const reader = new FileReader();
            reader.onload = e => {
                this.photoPreview = e.target.result;
                this.hasCurrentPhoto = true;
                this.runQrisQualityChecks();
            };
            reader.readAsDataURL(finalFile);
        },

        runQrisQualityChecks() {
            const img = new Image();
            img.onload = () => {
                this.resolutionOk = img.naturalWidth >= 200 && img.naturalHeight >= 200;
                this.checked = true;
                this.verified = this.resolutionOk && this.formatOk;
            };
            img.onerror = () => {
                this.resolutionOk = false;
                this.checked = true;
                this.verified = false;
            };
            img.src = this.photoPreview;
        },

        clearPhoto() {
            this.photoPreview = null;
            this.hasCurrentPhoto = false;
            this.currentPhotoUrl = '';
            this.checked = false;
            this.verified = false;
            this.resolutionOk = false;
            this.formatOk = false;
            this.$refs.qrisInput.value = '';
        },
    };
}
</script>
@endpush
@endsection
