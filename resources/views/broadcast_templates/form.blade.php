@extends('Layouts.app')

@php($isEditing = $template->exists)

@section('title', ($isEditing ? 'Edit' : 'Tambah') . ' Template Pesan')
@section('page-title', $isEditing ? 'Edit Template Pesan' : 'Tambah Template Pesan')
@section('subtitle', 'Buat teks siap pakai untuk campaign broadcast')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('broadcast-campaigns.templates.index') }}" class="btn-secondary p-2" aria-label="Kembali ke template">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
        </a>
        <div>
            <h2 class="text-lg font-extrabold text-slate-950">{{ $isEditing ? 'Perbarui Template' : 'Buat Template' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Tulis pesan siap pakai untuk campaign broadcast.</p>
        </div>
    </div>

    <div class="card p-6">
        <form method="POST"
              action="{{ $isEditing ? route('broadcast-campaigns.templates.update', $template) : route('broadcast-campaigns.templates.store') }}"
              class="space-y-4">
            @csrf
            @if($isEditing)
                @method('PATCH')
            @endif

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-800">Nama Template</label>
                <input id="name" name="name" type="text" maxlength="120" required value="{{ old('name', $template->name) }}" class="form-input" placeholder="Contoh: Pengingat Pengembalian">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="category" class="mb-1.5 block text-sm font-medium text-slate-800">Kategori</label>
                <input id="category" name="category" type="text" maxlength="80" required value="{{ old('category', $template->category) }}" class="form-input" placeholder="Contoh: Promosi atau Pengingat">
                @error('category')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="content" class="mb-1.5 block text-sm font-medium text-slate-800">Isi Pesan</label>
                <textarea id="content" name="content" rows="7" maxlength="2000" required class="form-input" placeholder="Tulis pesan">{{ old('content', $template->content) }}</textarea>
                @error('content')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="branch_id" class="mb-1.5 block text-sm font-medium text-slate-800">Cakupan Cabang</label>
                @if(auth()->user()->isSuperAdmin())
                    <select id="branch_id" name="branch_id" class="form-input">
                        <option value="">Semua cabang</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $template->branch_id ?? '') === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Template Semua cabang tersedia untuk semua cabang.</p>
                @else
                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">Semua cabang</p>
                    <p class="mt-1 text-xs text-slate-500">Template akan tersedia untuk semua cabang.</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                <button type="submit" class="btn-primary px-4 py-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    {{ $isEditing ? 'Simpan Perubahan' : 'Simpan Template' }}
                </button>
                <a href="{{ route('broadcast-campaigns.templates.index') }}" class="btn-secondary px-4 py-2">Batal</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>lucide.createIcons();</script>
@endpush
@endsection
