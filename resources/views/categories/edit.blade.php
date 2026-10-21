@extends('Layouts.app')

@section('title', 'Edit Kategori')

@section('content')
<div class="max-w-xl mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('categories.index') }}" class="btn-secondary p-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h1 class="font-playfair text-2xl font-bold" style="color: var(--text-dark)">Edit Kategori</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-soft)">{{ $category->name }}</p>
        </div>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-4">
            @csrf @method('PATCH')

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Nama Kategori <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}"
                    class="form-input @error('name') border-red-400 @enderror">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Slug <span class="text-red-500">*</span></label>
                <input type="text" name="slug" value="{{ old('slug', $category->slug) }}"
                    class="form-input @error('slug') border-red-400 @enderror">
                @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Icon (Fixed Default: shirt) --}}
            <div>
                <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Icon</label>
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/70">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600 shrink-0 border border-blue-200">
                        <i data-lucide="shirt" class="w-5 h-5"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium" style="color:var(--text-dark)">Jas & Kemeja (shirt)</p>
                        <p class="text-xs text-slate-400">Icon standar persewaan jas</p>
                    </div>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-600 font-medium border border-blue-100 shrink-0">
                        Default
                    </span>
                </div>
                <input type="hidden" name="icon" value="shirt">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Urutan Tampil</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}"
                    class="form-input" min="1">
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                    {{ old('is_active', $category->is_active) ? 'checked' : '' }}
                    class="w-4 h-4 rounded">
                <label for="is_active" class="text-sm font-medium" style="color:var(--text-dark)">Kategori Aktif</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i> Perbarui
                </button>
                <a href="{{ route('categories.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
lucide.createIcons();
</script>
@endpush
@endsection
