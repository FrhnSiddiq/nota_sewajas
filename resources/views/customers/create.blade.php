@extends('Layouts.app')

@section('title', isset($customer) ? 'Edit Pelanggan' : 'Tambah Pelanggan')
@section('page-title', isset($customer) ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru')
@section('subtitle', isset($customer) ? $customer->name : 'Input data pelanggan baru')

@section('content')
<div class="max-w-2xl mx-auto space-y-4 sm:space-y-5">

<div class="flex items-center gap-3">
        <a href="{{ isset($customer) ? route('customers.show', $customer) : route('customers.index') }}" class="btn-secondary w-fit max-w-full">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            {{ isset($customer) ? 'Kembali ke Detail Pelanggan' : 'Kembali ke Pelanggan' }}
        </a>
    </div>

    @php
        $phoneOld = preg_replace('/\D+/', '', (string) old('phone', $customer->phone ?? ''));
        if (str_starts_with($phoneOld, '62')) {
            $phoneOld = substr($phoneOld, 2);
        } elseif (str_starts_with($phoneOld, '0')) {
            $phoneOld = substr($phoneOld, 1);
        }
    @endphp


    <form method="POST" action="{{ isset($customer) ? route('customers.update', $customer) : route('customers.store') }}"
          enctype="multipart/form-data" x-data="customerForm()">
        @csrf
        @if(isset($customer)) @method('PATCH') @endif

        <!-- Basic Info -->
        <div class="ds-card p-4 sm:p-6">
            <h3 class="font-semibold text-base mb-4 sm:mb-5" style="color: var(--text-dark)">
                <i data-lucide="user" class="w-4 h-4 inline mr-2" style="color: var(--primary)"></i>
                Informasi Dasar
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="customer-name" class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Nama Lengkap <span class="text-red-400">*</span></label>
                    <input id="customer-name" type="text" name="name" value="{{ old('name', $customer->name ?? '') }}"
                           class="form-input" placeholder="Nama lengkap pelanggan" required>
                    @error('name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="customer-phone" class="block text-sm font-medium mb-1.5" style="color: var(--text-dark)">Nomor Handphone <span class="text-red-400">*</span></label>
                    <div class="input-group-phone">
                        <span class="input-group-phone__prefix">+62</span>
                        <input id="customer-phone" type="text"
                               name="phone"
                               value="{{ $phoneOld }}"
                               class="input-group-phone__input"
                               placeholder="8123456789"
                               required
                               inputmode="numeric"
                               x-on:input="filterPhoneInput($event)"
                               x-on:keydown="preventNonNumeric($event)"
                               autocomplete="tel">
                    </div>
                    <p class="text-xs text-stone-500 mt-1" style="color: var(--text-soft)">Format: angka saja. Prefix +62 akan disimpan otomatis.</p>
                    @error('phone')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror

                    @if(session('deleted_customer'))
                        <div class="mt-3 ds-card p-4 bg-amber-50 border border-amber-200">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100">
                                    <i data-lucide="info" class="w-5 h-5 text-amber-600"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-amber-900">Pelanggan Ditemukan</p>
                                    <div class="mt-2 space-y-1 text-sm text-amber-800 break-words">
                                        <p><span class="font-medium">Nama:</span> {{ session('deleted_customer.name') }}</p>
                                        <p><span class="font-medium">Nomor HP:</span> {{ session('deleted_customer.phone') }}</p>
                                        <p><span class="font-medium">Dinonaktifkan:</span> {{ session('deleted_customer.deleted_at') }}</p>
                                    </div>
                                    <p class="text-xs text-amber-700 mt-2">Pelanggan ini telah dinonaktifkan.</p>
                                    <button type="submit" form="restore-deleted-customer-form" class="btn-primary mt-3 w-full justify-center sm:w-auto">
                                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                        Pulihkan Pelanggan
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Submit -->
            <div class="customer-form-actions mt-5 flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ isset($customer) ? route('customers.show', $customer) : route('customers.index') }}" class="btn-secondary w-full justify-center sm:w-auto">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Batal
                </a>
                <button type="submit" class="btn-primary w-full justify-center sm:w-auto">
                    <i data-lucide="{{ isset($customer) ? 'save' : 'user-plus' }}" class="w-4 h-4"></i>
                    {{ isset($customer) ? 'Simpan Perubahan' : 'Tambah Pelanggan' }}
                </button>
            </div>
        </div>

    </form>
    @if (session('deleted_customer'))
        <form id="restore-deleted-customer-form" method="POST" action="{{ route('customers.restore', session('deleted_customer.id')) }}" class="hidden">
            @csrf
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
function customerForm() {
    return {
        idPhotoPreview: null,
        hasCurrentIdPhoto: {{ isset($customer) && $customer->id_photo ? 'true' : 'false' }},
        currentIdPhotoUrl: '{{ isset($customer) && $customer->id_photo ? asset("storage/".$customer->id_photo) : "" }}',

        preventNonNumeric(event) {
            // Allow: backspace, delete, tab, escape, enter
            if ([8, 46, 9, 27, 13].includes(event.keyCode) ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (event.keyCode === 65 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode === 67 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode === 86 && (event.ctrlKey || event.metaKey)) ||
                (event.keyCode === 88 && (event.ctrlKey || event.metaKey)) ||
                // Allow: home, end, left, right
                (event.keyCode >= 35 && event.keyCode <= 39)) {
                return; // let it happen, don't do anything
            }

            // Ensure that it is a number and stop the keypress
            if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) && (event.keyCode < 96 || event.keyCode > 105)) {
                event.preventDefault();
            }
        },

        filterPhoneInput(event) {
            const input = event.target;
            let digits = input.value.replace(/[^0-9]/g, ''); // Keep only digits

            // The +62 prefix is displayed separately, so keep only the local number here.
            if (digits.startsWith('62')) {
                digits = digits.slice(2);
            } else if (digits.startsWith('0')) {
                digits = digits.slice(1);
            }

            input.value = digits;
        },

        previewIdPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                this.idPhotoPreview = e.target.result;
                this.hasCurrentIdPhoto = true;
            };
            reader.readAsDataURL(file);
        },

        clearIdPhoto() {
            this.idPhotoPreview = null;
            this.hasCurrentIdPhoto = false;
            this.$refs.idPhotoInput.value = '';
        }
    }
}
</script>
@endpush
