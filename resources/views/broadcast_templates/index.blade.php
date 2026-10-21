@extends('Layouts.app')

@section('title', 'Template Pesan - Broadcast')
@section('page-title', 'Template Pesan')
@section('subtitle', 'Kelola teks siap pakai untuk campaign broadcast')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-950">Template Pesan</h2>
            <p class="mt-1 text-sm text-slate-500">Template ini adalah teks siap pakai, bukan template resmi WhatsApp Business API.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('broadcast-campaigns.index') }}" class="btn-secondary px-4 py-2">Kembali ke Broadcast</a>
            <a href="{{ route('broadcast-campaigns.templates.create') }}" class="btn-primary px-4 py-2">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Tambah Template
            </a>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white shadow-card-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Kategori</th>
                        <th class="px-5 py-3 font-semibold">Isi Pesan</th>
                        <th class="px-5 py-3 font-semibold">Cakupan</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($templates as $template)
                        <tr class="align-top hover:bg-slate-50/60">
                            <td class="px-5 py-3 font-semibold text-slate-900">{{ $template->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $template->category }}</td>
                            <td class="px-5 py-3 text-slate-600">
                                <p class="max-w-lg whitespace-pre-wrap">{{ $template->content }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $template->branch?->name ?? 'Semua cabang' }}</td>
                            <td class="px-5 py-3">
                                <span class="badge {{ $template->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if(auth()->user()->isSuperAdmin()
                                    || (int) $template->branch_id === (int) auth()->user()->branch_id
                                    || ($template->branch_id === null && (int) $template->created_by === (int) auth()->id()))
                                    <div class="inline-flex flex-wrap justify-end gap-1">
                                        <a href="{{ route('broadcast-campaigns.templates.edit', $template) }}" class="btn-ghost px-2 py-1 text-xs">Edit</a>
                                        <form method="POST" action="{{ route('broadcast-campaigns.templates.toggle', $template) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-ghost px-2 py-1 text-xs">
                                                {{ $template->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('broadcast-campaigns.templates.destroy', $template) }}" onsubmit="return confirm('Arsipkan template ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-ghost px-2 py-1 text-xs text-red-600 hover:text-red-700">Arsipkan</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Template global</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">Belum ada template pesan. Tambahkan template pertama Anda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $templates->links() }}</div>
        @endif
    </div>
</div>

@push('scripts')
<script>lucide.createIcons();</script>
@endpush
@endsection
