@extends('Layouts.app')

@section('title', $customer->name)
@section('page-title', 'Detail Pelanggan')
@section('subtitle', $customer->name)

@push('styles')
<style>
.rental-status-returned{background:#F0FDF4}
.rental-status-overdue{background:#FFF1F0}
.rental-status-active{background:#EFF6FF}
.rental-status-default{background:#FFF8E7}
.rental-status-icon-returned{color:#15803D}
.rental-status-icon-overdue{color:#C0392B}
.rental-status-icon-active{color:#1D4ED8}
.rental-status-icon-default{color:#B7791F}
.btn-blacklist{background:linear-gradient(135deg,#EF4444,#DC2626);box-shadow:0 2px 8px rgba(239,68,68,0.3)}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center}
.modal-box{background:white;border-radius:1rem;padding:1.5rem;max-width:40rem;width:90%;max-height:90vh;overflow:auto}
</style>
@endpush

@section('content')
<div class="space-y-5" x-data="{ showBlacklist: false, showPhotoPreview: false }">
@php $isDeactivated = $customer->trashed(); @endphp

@if($isDeactivated)
<div class="flex items-center gap-3 p-4 rounded-xl bg-amber-50 border border-amber-200">
<i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 text-amber-600"></i>
<div class="flex-1">
<p class="font-semibold text-amber-800">Pelanggan ini dalam status Dinonaktifkan</p>
@if($customer->deleted_at)<p class="text-sm text-amber-600">Dinonaktifkan pada: {{ $customer->deleted_at->format('d M Y H:i') }}</p>@endif
</div>
@auth @if(auth()->user()->isSuperAdmin())
<form method="POST" action="{{ route('customers.restore', $customer->id) }}" class="inline">
@csrf
<button type="submit" class="btn-secondary bg-amber-100 border-amber-300 text-amber-800 hover:bg-amber-200"><i data-lucide="rotate-ccw" class="w-4 h-4"></i> Pulihkan Pelanggan</button>
</form>
@endif @endauth
</div>
@endif

<div class="grid grid-cols-2 items-stretch gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
<a href="{{ route('customers.index') }}" class="btn-secondary flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm sm:w-auto"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
@if(!$isDeactivated)
@can('update', $customer)
<a href="{{ route('customers.edit', $customer) }}" class="btn-secondary flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm sm:w-auto"><i data-lucide="edit-2" class="w-4 h-4"></i> Edit</a>
@endcan
<a href="{{ route('rentals.create', ['customer_id' => $customer->id]) }}" class="btn-primary col-span-2 flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm sm:col-span-1 sm:w-auto"><i data-lucide="plus" class="w-4 h-4"></i> Buat Penyewaan Baru</a>
<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', str_starts_with($customer->phone, '0') ? '62'.substr($customer->phone,1) : $customer->phone) }}" target="_blank" class="btn-secondary flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm sm:w-auto" style="color:#25D366;border-color:#25D366"><i data-lucide="message-circle" class="w-4 h-4"></i> WhatsApp</a>
@if(auth()->user()->isSuperAdmin())
<button @click="showBlacklist = true" class="btn-secondary flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm sm:w-auto {{ $customer->is_blacklisted ? 'border-green-400 text-green-600' : 'border-red-300 text-red-500' }}"><i data-lucide="{{ $customer->is_blacklisted ? 'user-check' : 'user-x' }}" class="w-4 h-4"></i> {{ $customer->is_blacklisted ? 'Hapus Blacklist' : 'Blacklist' }}</button>
@endif
@can('delete', $customer)
<button type="button" onclick="confirmDelete('deleteCustomerForm','customer {{ $customer->name }}')" class="btn-secondary flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm text-red-500 hover:bg-red-50 sm:w-auto"><i data-lucide="trash-2" class="w-4 h-4"></i> Hapus</button>
@endcan
@else
@auth @if(auth()->user()->isSuperAdmin())
<button type="button" onclick="confirmDelete('deleteCustomerForm','customer {{ $customer->name }}')" class="btn-secondary col-span-2 flex min-h-11 w-full items-center justify-center whitespace-nowrap px-3 text-sm text-red-500 hover:bg-red-50 sm:col-span-1 sm:w-auto"><i data-lucide="trash-2" class="w-4 h-4"></i> Hapus Permanen</button>
@endif @endauth
@endif
</div>

<div class="flex items-center gap-2 flex-wrap">
@if($isDeactivated) <span class="badge bg-amber-100 text-amber-700 border border-amber-200">Dinonaktifkan</span>
@elseif($customer->is_blacklisted) <span class="badge badge-red">Blacklist</span>
@else <span class="badge badge-green">Aktif</span>
@endif
</div>

@if($customer->is_blacklisted)
<div class="flex items-center gap-3 p-4 rounded-xl" style="background:#FFF1F0;border:1px solid #FECACA">
<i data-lucide="shield-off" class="w-5 h-5 flex-shrink-0" style="color:#C0392B"></i>
<div><p class="font-semibold" style="color:#C0392B">Pelanggan ini diblokir</p>
@if($customer->blacklist_reason)<p class="text-sm" style="color:#E74C3C">Alasan: {{ $customer->blacklist_reason }}</p>@endif
</div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-1">
        <div class="ds-card p-6">
            <h3 class="font-semibold text-base mb-4" style="color:var(--text-dark)">
                <i data-lucide="user" class="w-4 h-4 inline mr-2" style="color:var(--primary)"></i> Informasi Pelanggan
            </h3>

            <div class="flex flex-col items-center mb-5">
                <div class="w-24 h-24 rounded-full border border-gold/30 bg-gold text-bark flex items-center justify-center font-bold text-2xl shadow-sm ds-hover-lift {{ $isDeactivated ? 'grayscale' : '' }}">
                    {{ strtoupper(substr($customer->name, 0, 2)) }}
                </div>
            </div>

            <div class="space-y-4">
                <div><p class="text-xs text-stone-500 mb-1">Nama Lengkap</p><p class="text-sm font-semibold text-bark-dark">{{ $customer->name }}</p></div>
                <div><p class="text-xs text-stone-500 mb-1">Nomor WhatsApp</p><p class="text-sm font-semibold text-bark-dark">{{ $customer->phone }}</p></div>
                <hr class="border-cream-sand/50 my-3">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-xs text-stone-500 mb-1">Total Sewa</p><p class="text-lg font-bold text-bark-dark">{{ $customer->rentals->count() }}</p></div>
                    <div class="text-right"><p class="text-xs text-stone-500 mb-1">Selesai</p><p class="text-lg font-bold text-green-600">{{ $customer->rentals->filter(fn ($rental) => $rental->isCompleted())->count() }}</p></div>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="ds-card overflow-hidden">
<div class="border-b p-4 sm:p-5" style="border-color:var(--border)">
<div class="flex items-center justify-between gap-3">
<h3 class="font-semibold text-base" style="color:var(--text-dark)"><i data-lucide="shirt" class="w-4 h-4 inline mr-2" style="color:var(--primary)"></i> Riwayat Penyewaan</h3>
<a href="{{ route('rentals.index', ['customer_id' => $customer->id]) }}" class="text-xs font-semibold hover:underline" style="color:var(--primary)">Lihat Semua →</a>
</div>
</div>
<div class="divide-y divide-cream-sand/30">
@forelse($customer->rentals as $rental)
<div class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 p-3 transition-colors hover:bg-cream/10 ds-transition sm:flex sm:items-center sm:gap-4 sm:p-4">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 rental-status-{{ $rental->rental_status }}">
        <i data-lucide="{{ $rental->hasOutstandingFine() && $rental->rental_status === 'returned' ? 'alert-circle' : match($rental->rental_status) { 'returned' => 'check-circle', 'overdue' => 'alert-circle', 'active' => 'clock', default => 'hourglass' } }}" class="w-5 h-5 {{ $rental->rental_status === 'returned' && !$rental->isCompleted() ? 'text-amber-600' : 'rental-status-icon-'.$rental->rental_status }}"></i>
    </div>
    <div class="min-w-0 sm:flex-1">
        <div class="flex items-center gap-2">
            <a href="{{ route('rentals.show', $rental) }}" class="break-all font-mono text-sm font-semibold hover:underline" style="color:var(--primary)">{{ $rental->invoice_number }}</a>
            <span class="badge badge-{{ $rental->status_badge_color }} shrink-0 text-[10px]">{{ $rental->status_label }}</span>
        </div>
        <p class="mt-0.5 break-words text-xs" style="color:var(--text-soft)">{{ optional($rental->rental_date)->format('d M Y') }} → {{ optional($rental->return_due_date)->format('d M Y') }} ({{ $rental->duration_days }} hari)</p>
        <div class="flex flex-wrap gap-1 mt-1">
            @foreach($rental->items->take(3) as $item)
            <span class="max-w-full break-words rounded-lg px-1.5 py-0.5 text-[10px]" style="background:var(--secondary);color:var(--text-soft)">{{ $item->product_name }}</span>
            @endforeach
        </div>
    </div>
    <div class="col-span-2 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 text-left sm:ml-auto sm:block sm:flex-shrink-0 sm:border-0 sm:pt-0 sm:text-right">
        <p class="font-bold text-sm" style="color:var(--text-dark)">Rp {{ number_format($rental->total_amount, 0, ',', '.') }}</p>
        <span class="badge {{ match($rental->payment_status) { 'paid' => 'badge-green', 'partial' => 'badge-yellow', default => 'badge-red' } }} text-[10px] mt-1">{{ $rental->payment_status_label }}</span>
        @if(($rental->fine_status === 'unpaid' || $rental->fine_status === 'partial') && !($rental->rental_status === 'returned' && $rental->hasOutstandingFine()))
            <span class="badge badge-yellow text-[10px] mt-1 ml-1">Ada Denda</span>
        @endif
    </div>
</div>
@empty
<div class="py-12 text-center">
<i data-lucide="shirt" class="w-8 h-8 mx-auto mb-2" style="color:var(--border)"></i>
<p class="text-sm" style="color:var(--text-soft)">Belum ada riwayat penyewaan</p>
</div>
@endforelse
</div>
</div>

<div x-show="showBlacklist" x-cloak class="modal-overlay" @click.self="showBlacklist = false">
<div class="modal-box">
<h3 class="font-semibold text-base mb-4" style="color:var(--text-dark)">{{ $customer->is_blacklisted ? 'Hapus dari Daftar Blokir' : 'Daftar Blokir Pelanggan' }}</h3>
<form method="POST" action="{{ route('customers.blacklist', $customer) }}">
@csrf @method('PATCH')
@if(!$customer->is_blacklisted)
<div class="mb-4">
<label class="block text-sm font-medium mb-1.5" style="color:var(--text-dark)">Alasan Daftar Blokir</label>
<textarea name="reason" rows="3" class="form-input" placeholder="Tulis alasan daftar blokir..." required></textarea>
</div>
@endif
<div class="flex gap-3">
<button type="button" @click="showBlacklist = false" class="btn-secondary flex-1 justify-center">Batal</button>
<button type="submit" class="btn-primary flex-1 justify-center {{ $customer->is_blacklisted ? '' : 'btn-blacklist' }}"><i data-lucide="{{ $customer->is_blacklisted ? 'user-check' : 'user-x' }}" class="w-4 h-4"></i> {{ $customer->is_blacklisted ? 'Hapus Daftar Blokir' : 'Daftar Blokir Pelanggan' }}</button>
</div>
</form>
</div>
</div>
@endsection

<form id="deleteCustomerForm" method="POST" action="{{ $isDeactivated ? route('customers.force-destroy', $customer) : route('customers.destroy', $customer) }}" class="hidden">@csrf @method('DELETE')</form>

@push('scripts')
<script>
</script>
@endpush
