@extends('Layouts.app')
@section('title', 'Maintenance Produk')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-bold text-2xl tracking-tight" style="color: var(--text-dark)">Maintenance Produk</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-soft)">Barang rusak / hilang yang perlu ditangani</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Produk
        </a>
    </div>

    <form method="GET" class="ds-card w-full min-w-0 max-w-full box-border p-4 sm:p-5 flex flex-col gap-3 sm:grid sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
        <div class="relative w-full min-w-0 max-w-full">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari produk / kode..." class="form-input ds-input-icon block w-full min-w-0 max-w-full box-border">
        </div>
        <div class="{{ request('search') ? 'grid grid-cols-2' : 'grid grid-cols-1' }} sm:flex gap-2 min-w-0">
            <button type="submit" class="btn-primary w-full justify-center whitespace-nowrap sm:w-auto">
                <i data-lucide="filter" class="w-4 h-4"></i> Filter
            </button>
            @if(request('search'))
                <a href="{{ route('products.maintenance') }}" class="btn-secondary w-full justify-center whitespace-nowrap sm:w-auto">
                    <i data-lucide="x" class="w-4 h-4"></i> Reset
                </a>
            @endif
        </div>
    </form>

    <div class="ds-card overflow-hidden">
        @if($items->isEmpty())
            <div class="p-12 text-center">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--secondary)">
                    <i data-lucide="wrench" class="w-8 h-8" style="color:var(--primary)"></i>
                </div>
                <p class="font-semibold text-lg" style="color:var(--text-dark)">Tidak ada item maintenance</p>
                <p class="text-sm mt-1" style="color:var(--text-soft)">Barang rusak/hilang akan muncul di sini setelah pengembalian.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50">Produk</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Ukuran</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50">Kondisi</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Qty</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-right">Denda</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50">Invoice</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50">Tanggal</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50">Catatan</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $row)
                        @php
                            $conditionMeta = [
                                'hilang'       => ['label' => 'Hilang',       'badge' => 'badge badge-red text-xs'],
                                'rusak_berat'  => ['label' => 'Rusak Berat',  'badge' => 'badge badge-red text-xs'],
                                'rusak_ringan' => ['label' => 'Rusak Ringan', 'badge' => 'badge badge-gold text-xs'],
                            ][$row->condition] ?? [
                                'label' => ucwords(str_replace('_', ' ', (string) $row->condition)),
                                'badge' => 'badge badge-blue-soft text-xs',
                            ];

                            // Catatan yang hanya mengulang label kondisi (mis. "hilang", "Rusak Berat")
                            // tidak ditampilkan agar informasi kondisi tidak tampil dua kali.
                            $notes = trim((string) $row->notes);
                            $notesRedundant = $notes !== '' && in_array(
                                \Illuminate\Support\Str::lower($notes),
                                [
                                    \Illuminate\Support\Str::lower((string) $row->condition),
                                    \Illuminate\Support\Str::lower($conditionMeta['label']),
                                ],
                                true
                            );
                        @endphp
                        <tr>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-sm" style="color:var(--text-dark)">{{ $row->product->name ?? '-' }}</p>
                                <p class="text-xs" style="color:var(--text-soft)">{{ $row->product->code ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-center"><span class="badge badge-blue-soft text-xs">{{ $row->size }}</span></td>
                            <td class="px-5 py-3.5">
                                <span class="{{ $conditionMeta['badge'] }}">{{ $conditionMeta['label'] }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-center font-semibold">{{ $row->quantity }}</td>
                            <td class="px-5 py-3.5 text-right font-semibold">Rp {{ number_format($row->damage_fee, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5 text-xs">{{ $row->rental->invoice_number ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-xs whitespace-nowrap">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3.5 text-xs" style="color:var(--text-soft)">{{ ($notes === '' || $notesRedundant) ? '—' : $notes }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <form method="POST" id="restoreMaintenance_{{ $row->id }}" action="{{ route('products.maintenance.restore', $row) }}">
                                        @csrf @method('PATCH')
                                        <button type="button" onclick="confirmAction('restoreMaintenance_{{ $row->id }}', 'Pulihkan Stok?', 'Stok barang ini akan dikembalikan ke stok tersedia.', 'Ya, Restore', '#2563eb')" class="btn-primary px-3 py-2 text-xs">
                                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Restore
                                        </button>
                                    </form>
                                    <form method="POST" id="disposeMaintenance_{{ $row->id }}" action="{{ route('products.maintenance.dispose', $row) }}">
                                        @csrf @method('DELETE')
                                        <button type="button" onclick="confirmAction('disposeMaintenance_{{ $row->id }}', 'Hapus Item Maintenance?', 'Stok total akan berkurang permanen. Tindakan ini tidak dapat dibatalkan.', 'Ya, Hapus', '#dc2626')" class="btn-secondary px-3 py-2 text-xs text-red-500 hover:bg-red-50">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t" style="border-color:var(--border)">
                {{ $items->links('components.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
