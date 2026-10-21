@extends('Layouts.app')
@section('title','Edit Pengguna — ' . $user->name)
@section('page-title','Edit Pengguna')
@section('subtitle','Ubah data akun pengguna')

@section('content')
<div class="space-y-6">

    {{-- ── HEADER ──────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('users.show', $user) }}" class="btn-secondary p-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <div>
                <h1 class="font-playfair text-2xl font-bold" style="color:var(--text-dark)">Edit Pengguna</h1>
                <p class="text-sm" style="color:var(--text-soft)">{{ $user->email }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge {{ $user->is_active ? 'badge-green' : 'badge-red' }}">
                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
            <span class="badge
                {{ match($user->role) {
                    'super_admin' => 'badge-gold',
                    'admin_toko'  => 'badge-blue',
                    'sales'       => 'badge-green',
                    default       => 'badge-gray'
                } }}">
                {{ ucfirst(str_replace('_', ' ', $user->role ?? '-')) }}
            </span>
        </div>
    </div>

    {{-- ── FORM ─────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ── KOLOM KIRI (2/3) ─────────────────────────── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Informasi Akun --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="user" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Informasi Akun</h2>
                    </div>

                    {{-- Nama --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}"
                               class="form-input @error('name') border-red-400 @enderror">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-soft)"></i>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="form-input @error('email') border-red-400 @enderror" style="padding-left: 40px !important">
                        </div>
                        @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- No. HP --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">No. Telepon</label>
                        <div class="relative">
                            <i data-lucide="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-soft)"></i>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                                   inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   class="form-input @error('phone') border-red-400 @enderror" style="padding-left: 40px !important">
                        </div>
                        @error('phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Password --}}
                <div class="card p-6 space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="lock" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Ganti Password</h2>
                            <p class="text-xs mt-0.5" style="color:var(--text-soft)">Kosongkan jika tidak ingin mengubah password</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Password Baru</label>
                            <div class="relative">
                                <input type="password" name="password" id="password"
                                       placeholder="Min. 8 karakter"
                                       class="form-input pr-10 @error('password') border-red-400 @enderror">
                                <button type="button" onclick="togglePassword('password','eye-password')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <i data-lucide="eye" id="eye-password" class="w-4 h-4" style="color:var(--text-soft)"></i>
                                </button>
                            </div>
                            @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Konfirmasi Password</label>
                            <div class="relative">
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                       placeholder="Ulangi password baru"
                                       class="form-input pr-10">
                                <button type="button" onclick="togglePassword('password_confirmation','eye-confirm')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <i data-lucide="eye" id="eye-confirm" class="w-4 h-4" style="color:var(--text-soft)"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── KOLOM KANAN (1/3) ────────────────────────── --}}
            <div class="space-y-6">

                {{-- Foto Avatar --}}
                <div class="card p-6 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="image" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Foto Profil</h2>
                    </div>

                    <div class="flex flex-col items-center gap-4">
                        <div class="w-24 h-24 rounded-2xl overflow-hidden relative group cursor-pointer"
                             onclick="document.getElementById('avatar-input').click()">
                            <img id="avatar-preview"
                                 src="{{ $user->avatar_url }}"
                                 alt="{{ $user->name }}"
                                 class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                            </div>
                        </div>
                        <div class="w-full border-2 border-dashed rounded-xl p-3 text-center cursor-pointer"
                             style="border-color:var(--border)"
                             onclick="document.getElementById('avatar-input').click()">
                            <p class="text-xs" style="color:var(--text-soft)">
                                <span class="font-medium" style="color:var(--primary)">Klik</span> untuk ganti foto
                            </p>
                            <input type="file" id="avatar-input" name="avatar" accept="image/*" class="hidden">
                        </div>
                    </div>
                    @error('avatar')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Role & Cabang --}}
                <div class="card p-6 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b" style="border-color:var(--border)">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:var(--secondary)">
                            <i data-lucide="shield" class="w-3.5 h-3.5" style="color:var(--primary)"></i>
                        </div>
                        <h2 class="font-semibold text-sm" style="color:var(--text-dark)">Hak Akses</h2>
                    </div>

                    {{-- Role --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="role" id="role-select"
                                onchange="toggleBranch(this.value)"
                                class="form-input @error('role') border-red-400 @enderror">
                            @foreach($roles as $role)
                            <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                            @endforeach
                        </select>
                        @error('role')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror

                        <div id="role-info" class="mt-2 rounded-xl p-3 text-xs hidden" style="background:var(--secondary)">
                            <p id="role-info-text" style="color:var(--text-soft)"></p>
                        </div>
                    </div>

                    {{-- Tipe Akses Cabang --}}
                    <div id="branch-access-type-wrap" class="{{ $user->role === 'admin_toko' ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium mb-2" style="color:var(--text-dark)">
                            Tipe Akses Cabang <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 rounded-xl p-3 border cursor-pointer transition-colors branch-access-option"
                                    style="border-color:var(--border)"
                                    id="radio-all-wrap">
                                <input type="radio" name="branch_access_type" value="all"
                                       {{ old('branch_access_type', $user->branch_access_type) === 'all' ? 'checked' : '' }}
                                       onchange="toggleBranchAccessType()"
                                       class="accent-blue-600">
                                <div>
                                    <p class="text-sm font-medium" style="color:var(--text-dark)">Semua Cabang</p>
                                    <p class="text-xs" style="color:var(--text-soft)">User dapat melihat dan mengelola data di semua cabang.</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl p-3 border cursor-pointer transition-colors branch-access-option"
                                    style="border-color:var(--border)"
                                    id="radio-specific-wrap">
                                <input type="radio" name="branch_access_type" value="specific"
                                       {{ old('branch_access_type', $user->branch_access_type) !== 'all' ? 'checked' : '' }}
                                       onchange="toggleBranchAccessType()"
                                       class="accent-blue-600">
                                <div>
                                    <p class="text-sm font-medium" style="color:var(--text-dark)">Cabang Tertentu</p>
                                    <p class="text-xs" style="color:var(--text-soft)">User hanya dapat mengakses cabang yang dipilih di bawah ini.</p>
                                </div>
                            </label>
                        </div>
                        @error('branch_access_type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- Daftar Cabang --}}
                    <div id="branch-list-wrap" class="{{ $user->role === 'super_admin' ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">
                            Cabang Utama / Cabang yang Diizinkan <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2 max-h-60 overflow-y-auto rounded-xl border p-3"
                             style="border-color:var(--border)" id="branch-checkbox-list">
                            @foreach($branches as $b)
                            <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 cursor-pointer hover:bg-slate-50 transition-colors">
                                <input type="checkbox" name="branch_ids[]" value="{{ $b->id }}"
                                       {{ in_array($b->id, old('branch_ids', $user->accessibleBranches->pluck('id')->all())) ? 'checked' : '' }}
                                       class="accent-blue-600 rounded">
                                <span class="text-sm" style="color:var(--text-dark)">{{ $b->name }}</span>
                                <span class="text-xs" style="color:var(--text-soft)">({{ $b->code ?? '-' }})</span>
                            </label>
                            @endforeach
                        </div>
                        <div class="space-y-2 max-h-60 overflow-y-auto rounded-xl border p-3 hidden"
                             style="border-color:var(--border)" id="branch-radio-list">
                            @foreach($branches as $b)
                            <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 cursor-pointer hover:bg-slate-50 transition-colors">
                                <input type="radio" name="branch_id" value="{{ $b->id }}"
                                       {{ old('branch_id', $user->branch_id) == $b->id ? 'checked' : '' }}
                                       class="accent-blue-600 rounded">
                                <span class="text-sm" style="color:var(--text-dark)">{{ $b->name }}</span>
                                <span class="text-xs" style="color:var(--text-soft)">({{ $b->code ?? '-' }})</span>
                            </label>
                            @endforeach
                        </div>
                        @error('branch_ids')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        @error('branch_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Info --}}
                <div class="card p-5 space-y-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="clock" class="w-3.5 h-3.5 flex-shrink-0" style="color:var(--text-soft)"></i>
                        <div>
                            <p class="text-xs" style="color:var(--text-soft)">Terakhir diperbarui</p>
                            <p class="text-sm font-medium" style="color:var(--text-dark)">{{ $user->updated_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 flex-shrink-0" style="color:var(--text-soft)"></i>
                        <div>
                            <p class="text-xs" style="color:var(--text-soft)">Terdaftar sejak</p>
                            <p class="text-sm font-medium" style="color:var(--text-dark)">{{ $user->created_at->format('d M Y') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full justify-center">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Perubahan
                    </button>
<a href="{{ route('users.show', $user) }}" class="btn-secondary w-full justify-center text-center">
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

    // Avatar preview (kompres bila perlu sebelum dikirim)
    document.getElementById('avatar-input').addEventListener('change', async function () {
        const file = this.files[0];
        if (!file) return;
        const finalFile = window.compressAndAssign
            ? await window.compressAndAssign(this, file)
            : file;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatar-preview').src = e.target.result;
        };
        reader.readAsDataURL(finalFile);
    });

    // Toggle password visibility
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }

    // Toggle cabang berdasarkan role
    const roleDesc = {
        'super_admin': 'Akses penuh ke semua cabang. Tidak perlu memilih cabang.',
        'admin_toko' : 'Akses semua cabang. Pilih cabang utama untuk transaksi akun ini.',
        'sales'      : 'Hanya transaksi harian. Pilih satu cabang yang diizinkan.',
    };

    function toggleBranch(role) {
        const accessWrap = document.getElementById('branch-access-type-wrap');
        const roleInfo = document.getElementById('role-info');
        const roleInfoText = document.getElementById('role-info-text');
        const radioAllWrap = document.getElementById('radio-all-wrap');
        const branchListWrap = document.getElementById('branch-list-wrap');
        const checkboxList = document.getElementById('branch-checkbox-list');
        const radioList = document.getElementById('branch-radio-list');
        const allOptions = document.querySelectorAll('.branch-access-option');

        if (role === 'super_admin') {
            accessWrap.classList.add('hidden');
            branchListWrap.classList.add('hidden');
        } else if (role === 'admin_toko') {
            accessWrap.classList.add('hidden');
            branchListWrap.classList.remove('hidden');
            checkboxList.classList.add('hidden');
            radioList.classList.remove('hidden');
        } else if (role === 'sales') {
            accessWrap.classList.remove('hidden');
            branchListWrap.classList.remove('hidden');
            accessWrap.style.opacity = '1';
            accessWrap.style.pointerEvents = 'auto';
            accessWrap.querySelectorAll('input').forEach(el => el.disabled = false);

            allOptions.forEach(el => {
                if (el.id === 'radio-all-wrap') {
                    el.classList.add('hidden');
                }
            });

            const specificRadio = document.querySelector('input[name="branch_access_type"][value="specific"]');
            if (specificRadio) specificRadio.checked = true;

            checkboxList.classList.add('hidden');
            radioList.classList.remove('hidden');
        } else {
            accessWrap.classList.remove('hidden');
            branchListWrap.classList.remove('hidden');
            accessWrap.style.opacity = '1';
            accessWrap.style.pointerEvents = 'auto';
            accessWrap.querySelectorAll('input').forEach(el => el.disabled = false);

            allOptions.forEach(el => el.classList.remove('hidden'));

            checkboxList.classList.remove('hidden');
            radioList.classList.add('hidden');
        }

        if (roleDesc[role]) {
            roleInfoText.textContent = roleDesc[role];
            roleInfo.classList.remove('hidden');
        } else {
            roleInfo.classList.add('hidden');
        }
    }

    function toggleBranchAccessType() {
        const type = document.querySelector('input[name="branch_access_type"]:checked')?.value;
        const branchListWrap = document.getElementById('branch-list-wrap');
        const radioAllWrap = document.getElementById('radio-all-wrap');
        const radioSpecificWrap = document.getElementById('radio-specific-wrap');
        const checkboxList = document.getElementById('branch-checkbox-list');
        const radioList = document.getElementById('branch-radio-list');
        const role = document.getElementById('role-select').value;

        if (role === 'admin_toko' || role === 'super_admin') {
            return;
        }

        if (!type) return;

        if (type === 'specific') {
            branchListWrap.classList.remove('hidden');
            radioSpecificWrap.style.borderColor = 'var(--primary)';
            radioSpecificWrap.style.background = 'rgba(99,102,241,0.04)';
            if (radioAllWrap) {
                radioAllWrap.style.borderColor = 'var(--border)';
                radioAllWrap.style.background = 'transparent';
            }
        } else {
            branchListWrap.classList.add('hidden');
            if (radioAllWrap) {
                radioAllWrap.style.borderColor = 'var(--primary)';
                radioAllWrap.style.background = 'rgba(99,102,241,0.04)';
            }
            radioSpecificWrap.style.borderColor = 'var(--border)';
            radioSpecificWrap.style.background = 'transparent';
        }

        if (role === 'sales') {
            checkboxList.classList.add('hidden');
            radioList.classList.remove('hidden');
        } else {
            checkboxList.classList.remove('hidden');
            radioList.classList.add('hidden');
        }
    }

    // Trigger on load
    const initialRole = document.getElementById('role-select').value;
    if (initialRole) toggleBranch(initialRole);
    toggleBranchAccessType();
</script>
@endpush
@endsection
