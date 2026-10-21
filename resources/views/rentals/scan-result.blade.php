@extends('Layouts.app')

@section('title', isset($rental) && $rental ? 'Hasil Scan QR - ' . $rental->invoice_number : 'Hasil Scan QR')

@section('content')
    @php
        $hasRental = isset($rental) && $rental;
        if ($hasRental) {
            $rentalData = [
                'id' => $rental->id,
                'invoice_number' => $rental->invoice_number,
                'rental_status' => $rental->rental_status,
                'payment_status' => $rental->payment_status,
                'fine_status' => $rental->fine_status,
                'fine_amount' => (float) ($rental->fine_amount ?? 0),
                'fine_paid_amount' => (float) ($rental->fine_paid_amount ?? 0),
                'returned_at' => $rental->returned_at?->format('d M Y H:i'),
                'returned_by' => optional($rental->returnedBy)->name ?? optional($rental->createdBy)->name,
                'created_by' => optional($rental->createdBy)->name,
                'total_amount' => (float) $rental->total_amount,
                'paid_amount' => (float) $rental->paid_amount,
                'remaining_amount' => (float) $rental->remaining_amount,
                'late_fee' => (float) ($rental->late_fee ?? 0),
                'subtotal' => $rental->subtotal ?? null,
                'discount' => $rental->discount ?? null,
                'deposit' => $rental->deposit ?? null,
                'return_due_date' => $rental->return_due_date?->format('d/m/Y'),
                'rental_date' => $rental->rental_date?->format('d/m/Y'),
                'duration_days' => $rental->duration_days ?? null,
                'actual_return_date' => $rental->actual_return_date?->format('d/m/Y'),
'customer' => [
                    'name' => optional($rental->customer)->name,
                    'phone' => optional($rental->customer)->phone,
                ],
                'branch' => [
                    'name' => optional($rental->branch)->name,
                ],
                'guarantees' => $rental->guarantees->map(function ($guarantee) {
                    return [
                        'id' => $guarantee->id,
                        'type' => $guarantee->type,
                        'type_label' => $guarantee->type_label ?? $guarantee->type,
                        'id_number' => $guarantee->id_number,
                        'deposit_amount' => (float) $guarantee->deposit_amount,
                        'status' => $guarantee->status,
                    ];
                }),
                'items' => $rental->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name ?? optional($item->product)->name ?? 'Produk #' . $item->product_id,
                        'size' => $item->product_size,
                        'category_name' => optional($item->product?->category)->name ?? '-',
                        'quantity' => $item->quantity,
                        'price' => (float) $item->price_per_day,
                        'subtotal' => (float) ($item->subtotal ?? (($item->price_per_day ?? 0) * ($item->quantity ?? 0))),
                        'photo' => $item->product && $item->product->photo ? asset('storage/'.$item->product->photo) : null,
                        'return_condition' => $item->return_condition,
                        'damage_fee' => (float) ($item->damage_fee ?? 0),
                        'return_notes' => $item->return_notes,
                        'is_returned' => (bool) $item->is_returned,
                    ];
                }),
                'activity_logs' => $rental->activityLogs->take(15)->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'description' => $log->description,
                        'user' => optional($log->user)->name,
                        'created_at' => $log->created_at?->format('d M Y H:i'),
                    ];
                }),
            ];
        }
    @endphp

    @if (!$hasRental)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center max-w-2xl mx-auto">
            <div class="flex justify-center mb-4">
                <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">QR Code Tidak Valid</h2>
            <p class="text-slate-600 mb-6 max-w-md mx-auto">{{ $error ?? 'Penyewaan tidak ditemukan dalam sistem.' }}</p>
            <div class="flex flex-wrap gap-3 justify-center">
                <a href="{{ route('rentals.scan') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Scan Lagi
                </a>
                <a href="{{ route('rentals.index') }}" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Semua Penyewaan
                </a>
            </div>
        </div>
    @else
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <div x-data="returnOperationalDashboard({{ \Illuminate\Support\Js::from($rentalData) }})" x-init="init()" class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            {{-- Header --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <a href="{{ route('rentals.scan') }}" class="p-2 rounded-xl border border-slate-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                        </a>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500">Hasil Scan QR</p>
                            <h1 class="text-2xl font-bold text-slate-900">Invoice <span x-text="rental.invoice_number"></span></h1>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 items-center">
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold" :class="badgeClass(rental.rental_status, 'rental')" x-text="statusBadgeText(rental.rental_status, 'rental')"></span>
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold" :class="badgeClass(rental.payment_status, 'payment')" x-text="statusBadgeText(rental.payment_status, 'payment')"></span>
                        <template x-if="rental.rental_status !== 'returned' && (rental.fine_status === 'unpaid' || rental.fine_status === 'partial')">
                            <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-amber-100 text-amber-700">Ada Denda Belum Dibayar</span>
                        </template>
                        <a href="{{ route('rentals.scan') }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 text-sm font-semibold transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Scan Lagi
                        </a>
                        <a href="{{ route('rentals.show', $rental) }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 text-sm font-semibold transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Detail Rental
                        </a>
                    </div>
                </div>

                {{-- Timeline Progress --}}
                <div class="mt-8">
                    <h3 class="text-sm font-semibold text-slate-700 mb-4">Progress Rental</h3>
                    <div class="relative flex items-center justify-between gap-2">
                        <div class="absolute left-0 right-0 top-4 h-1.5 rounded-full bg-slate-200"></div>
                        <div class="absolute left-0 top-4 h-1.5 rounded-full bg-blue-600 transition-all duration-500" :style="{ width: progressWidth + '%' }"></div>

                        {{-- Step 1 --}}
                        <div class="relative z-10 flex flex-col items-center flex-1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition-all duration-300" :class="progressStepClass(1)">
                                <template x-if="currentStep > 1">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </template>
                                <template x-if="currentStep <= 1">
                                    <span>1</span>
                                </template>
                            </div>
                            <span class="mt-2 text-center text-xs font-semibold text-slate-600">Booking</span>
                        </div>

                        {{-- Step 2 --}}
                        <div class="relative z-10 flex flex-col items-center flex-1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition-all duration-300" :class="progressStepClass(2)">
                                <template x-if="currentStep > 2">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </template>
                                <template x-if="currentStep <= 2">
                                    <span>2</span>
                                </template>
                            </div>
                            <span class="mt-2 text-center text-xs font-semibold text-slate-600">Sedang Disewa</span>
                        </div>

                        {{-- Step 3 --}}
                        <div class="relative z-10 flex flex-col items-center flex-1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition-all duration-300" :class="progressStepClass(3)">
                                <template x-if="currentStep > 3">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </template>
                                <template x-if="currentStep <= 3">
                                    <span>3</span>
                                </template>
                            </div>
                            <span class="mt-2 text-center text-xs font-semibold text-slate-600">Pemeriksaan</span>
                        </div>

                        {{-- Step 4 --}}
                        <div class="relative z-10 flex flex-col items-center flex-1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition-all duration-300" :class="progressStepClass(4)">
                                <template x-if="currentStep > 4">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </template>
                                <template x-if="currentStep <= 4">
                                    <span>4</span>
                                </template>
                            </div>
                            <span class="mt-2 text-center text-xs font-semibold text-slate-600">Selesai</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {{-- Left Column --}}
                <div class="space-y-6 xl:col-span-2">
                    {{-- Informasi Rental --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            Informasi Rental
                        </h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Invoice</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.invoice_number"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Customer</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.customer.name"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Telepon</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.customer.phone"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Cabang</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.branch.name"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Kasir</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.created_by"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Tanggal Sewa</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.rental_date"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Jatuh Tempo</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.return_due_date"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" x-show="rental.actual_return_date">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Tanggal Kembali</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.actual_return_date"></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" x-show="rental.duration_days">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Durasi</p>
                                <p class="text-sm font-bold text-slate-900" x-text="rental.duration_days + ' Hari'"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Daftar Barang --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            Daftar Barang
                        </h2>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <template x-for="item in rental.items" :key="'item-card-' + item.id">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition-all hover:border-blue-300 hover:bg-white">
                                    <div class="flex gap-4">
                                        <img :src="item.photo || defaultImage" x-on:error="$event.target.src = defaultImage" class="h-20 w-20 rounded-2xl border border-slate-200 object-cover" alt="Produk">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="truncate text-base font-semibold text-slate-900" x-text="item.product_name"></h3>
                                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                                <div class="flex flex-col gap-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kategori</p>
                                                    <p class="text-slate-700" x-text="item.category_name || '-'"></p>
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ukuran</p>
                                                    <p class="font-bold text-slate-900" x-text="item.size"></p>
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Qty</p>
                                                    <p class="font-bold text-slate-900" x-text="item.quantity"></p>
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subtotal</p>
                                                    <p class="font-bold text-slate-900" x-text="fmt(item.subtotal)"></p>
                                                </div>
                                                <div class="col-span-2 flex flex-col gap-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                                    <span class="w-fit inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="itemBadgeClass(getItemCondition(item.id))" x-text="itemConditionLabel(getItemCondition(item.id))"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Panel Operasional Pengembalian --}}
                    <template x-if="showOperationalPanel">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                            <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                    </div>
                                    Pemeriksaan Barang
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="controlsDisabled ? 'bg-slate-100 text-slate-600' : 'bg-blue-100 text-blue-700'" x-text="controlsDisabled ? 'Read Only' : 'Aktif'"></span>
                            </h2>

                            {{-- Quick Actions --}}
                            <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <button type="button" @click="markAllGood()" :disabled="controlsDisabled" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition-all hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span class="text-lg">✅</span>
                                    Tandai Semua Baik
                                </button>
                                <button type="button" @click="resetItems()" :disabled="controlsDisabled" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition-all hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span class="text-lg">🔄</span>
                                    Reset
                                </button>
                            </div>

                            {{-- Items Check --}}
                            <div class="space-y-4">
                                <template x-for="item in rental.items" :key="'inspection-' + item.id">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                            <h3 class="text-base font-semibold text-slate-900" x-text="item.product_name"></h3>
                                            <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold" :class="itemBadgeClass(getItemCondition(item.id))" x-text="itemConditionLabel(getItemCondition(item.id))"></span>
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                                            <button type="button" @click="setItemCondition(item.id, 'good')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'good' ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-600'">
                                                ✅ Baik
                                            </button>
                                            <button type="button" @click="setItemCondition(item.id, 'rusak_ringan')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'rusak_ringan' ? 'border-yellow-500 bg-yellow-50 text-yellow-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-600'">
                                                ⚠️ Rusak Ringan
                                            </button>
                                            <button type="button" @click="setItemCondition(item.id, 'rusak_berat')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'rusak_berat' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-600'">
                                                ⚠️ Rusak Berat
                                            </button>
                                            <button type="button" @click="setItemCondition(item.id, 'hilang')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'hilang' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-600'">
                                                ❌ Hilang
                                            </button>
                                        </div>

                                        <div x-show="needsFee(getItemCondition(item.id))" x-transition class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Denda</label>
                                                <div class="relative">
                                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-500">Rp</span>
                                                    <input type="number" min="0" x-model="itemDamageFees[item.id]" @input="calculateTotalDenda()" :disabled="controlsDisabled" class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-12 pr-4 text-sm text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100" placeholder="Masukkan nominal denda">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
                                                <textarea x-model="itemNotes[item.id]" :disabled="controlsDisabled" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100" placeholder="Catatan kondisi barang..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Konfirmasi Button --}}
                            <div class="mt-6">
                                <button type="button" @click="confirmReturn()" :disabled="confirmDisabled" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-6 py-4 text-base font-bold text-white shadow-sm transition-all"
                                        :class="confirmDisabled ? 'bg-slate-400 cursor-not-allowed' : 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 hover:scale-[1.005]'">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span x-text="controlsDisabled ? 'Pengembalian Sudah Diproses' : 'Konfirmasi Barang Sudah Dikembalikan'"></span>
                                </button>
                                <p class="mt-3 text-center text-xs text-slate-500">Submit dinonaktifkan jika ada item belum dipilih atau nominal denda wajib belum diisi.</p>
                            </div>
                        </div>
                    </template>

                    @if(auth()->user()->isSuperAdmin())
                    {{-- Aktivitas Log --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            Aktivitas
                        </h2>
                        <div class="space-y-3">
                            <template x-for="log in rental.activity_logs" :key="'log-' + log.id">
                                <div class="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-800" x-text="log.description"></p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            <span x-text="log.created_at"></span>
                                            <template x-if="log.user">
                                                <span>• <span x-text="log.user"></span></span>
                                            </template>
                                        </p>
                                    </div>
                                    @endif
                                </div>
                            </template>
                            <template x-if="rental.activity_logs.length === 0">
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                    Belum ada aktivitas.
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Right Column --}}
                <div class="space-y-6">
                    {{-- Informasi Jaminan --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            Informasi Jaminan
                        </h2>
                        <template x-if="rental.guarantees.length > 0">
                            <div class="space-y-3">
                                <template x-for="guarantee in rental.guarantees" :key="'guarantee-' + guarantee.id">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex flex-col gap-1">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Jaminan</p>
                                                <p class="text-sm font-bold text-slate-900" x-text="guarantee.type_label || guarantee.type"></p>
                                            </div>
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="guarantee.status === 'returned' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'" x-text="guarantee.status === 'returned' ? 'Sudah Dikembalikan' : 'Masih Ditahan'"></span>
                                        </div>
                                        <div class="mt-3 space-y-2 text-sm">
                                            <template x-if="guarantee.id_number">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-slate-500">Nomor</span>
                                                    <span class="font-semibold text-slate-800" x-text="guarantee.id_number"></span>
                                                </div>
                                            </template>
                                            <template x-if="guarantee.deposit_amount > 0">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-slate-500">Deposit</span>
                                                    <span class="font-semibold text-slate-800" x-text="fmt(guarantee.deposit_amount)"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="rental.guarantees.length === 0">
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                Tidak ada jaminan.
                            </div>
                        </template>
                    </div>

                    {{-- Ringkasan Pembayaran --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            Ringkasan Pembayaran
                        </h2>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600">Subtotal</span>
                                <span class="font-semibold text-slate-900" x-text="fmt(rental.subtotal || rental.total_amount)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600">Deposit</span>
                                <span class="font-semibold text-slate-900" x-text="fmt(rental.deposit || 0)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600">Diskon</span>
                                <span class="font-semibold text-slate-900" x-text="fmt(rental.discount || 0)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600">Sewa (tagihan)</span>
                                <span class="font-semibold text-slate-900" x-text="fmt(rental.total_amount || 0)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm" x-show="rental.fine_amount > 0">
                                <span class="text-slate-600">Denda</span>
                                <span class="font-semibold text-red-600" x-text="fmt(rental.fine_amount || 0)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm" x-show="rental.total_amount > 0">
                                <span class="text-slate-600">Status Sewa</span>
                                <span class="font-semibold" :class="paymentStatusClass(rental.payment_status)" x-text="paymentStatusLabel(rental.payment_status)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm" x-show="rental.fine_amount > 0">
                                <span class="text-slate-600">Status Denda</span>
                                <span class="font-semibold" :class="fineStatusClass(rental.fine_status)" x-text="fineStatusLabel(rental.fine_status)"></span>
                            </div>
                            <div class="border-t border-slate-200 pt-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-base font-bold uppercase tracking-wide text-slate-500">Grand Total</span>
                                    <span class="text-2xl font-extrabold text-blue-700" x-text="fmt(displayGrandTotal)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Sudah Dibayar</span>
                                    <span class="font-semibold text-slate-900" x-text="fmt(rental.paid_amount)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Sisa Sewa</span>
                                    <span class="font-semibold text-slate-900" x-text="fmt(rental.remaining_amount || 0)"></span>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        function returnOperationalDashboard(initialRental = null) {
            return {
                controlsDisabled: false,
                totalDenda: 0,
                defaultImage: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" fill="%23f1f5f9" rx="16"/><g transform="translate(20, 18)" fill="none" stroke="%2394a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4h10l10 10h4l-8 8v6h-24v-6z"/><path d="M10 14h24"/><path d="M14 14v6"/><path d="M34 14v6"/><circle cx="20" cy="28" r="2"/><circle cx="28" cy="28" r="2"/></g></svg>',
                progressSteps: [
                    { id: 1, label: 'Booking' },
                    { id: 2, label: 'Sedang Disewa' },
                    { id: 3, label: 'Pemeriksaan' },
                    { id: 4, label: 'Selesai' },
                ],
                rental: initialRental || null,
                paymentDraft: null,
                itemCondition: {},
                itemNotes: {},
                itemDamageFees: {},

                init() {
                    if (this.rental) {
                        (this.rental.items || []).forEach(item => {
                            const cond = item.return_condition;
                            if (cond) {
                                if (cond === 'baik') {
                                    this.itemCondition[item.id] = 'good';
                                } else if (cond === 'hilang') {
                                    this.itemCondition[item.id] = 'hilang';
                                } else {
                                    this.itemCondition[item.id] = cond;
                                }
                            }
                            this.itemNotes[item.id] = item.return_notes || '';
                            this.itemDamageFees[item.id] = Number(item.damage_fee || 0);
                        });

                        this.paymentDraft = this.rental.payment_status;
                        // Only disable initially if cancelled/waiting, OR if returned AND all items have condition set
                        const allItemsHaveCondition = (this.rental.items || []).every(item => item.return_condition);
                        this.controlsDisabled = 
                            this.rental.rental_status === 'cancelled' || 
                            this.rental.rental_status === 'waiting' || 
                            (this.rental.rental_status === 'returned' && allItemsHaveCondition);
                        this.calculateTotalDenda();
                    }
                },

                get showOperationalPanel() {
                    return this.rental && ['active', 'overdue', 'returned'].includes(this.rental.rental_status);
                },

                get currentStep() {
                    if (!this.rental) return 1;
                    if (this.rental.rental_status === 'returned') {
                        return this.rental.payment_status === 'paid'
                            && ['none', 'paid'].includes(this.rental.fine_status)
                            && Number(this.rental.fine_paid_amount || 0) >= Number(this.rental.fine_amount || 0)
                            ? 4
                            : 3;
                    }
                    if (this.rental.rental_status === 'active' || this.rental.rental_status === 'overdue') return 2;
                    return 1;
                },

                get progressWidth() {
                    if (this.currentStep === 4) return 100;
                    if (this.currentStep === 3) return 75;
                    if (this.currentStep === 2) return 50;
                    if (this.currentStep === 1) return 25;
                    return 0;
                },

                get displayFine() {
                    return Number(this.rental?.fine_amount || 0);
                },

                get displayGrandTotal() {
                    return Number(this.rental?.total_amount || 0) + Number(this.rental?.fine_amount || 0);
                },

                progressStepClass(step) {
                    if (this.currentStep >= step) {
                        return 'border-blue-600 bg-blue-600 text-white';
                    }
                    return 'border-slate-300 bg-white text-slate-400';
                },

                fmt(value) {
                    if (value == null || isNaN(Number(value))) return 'Rp 0';
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value));
                },

                badgeClass(status, kind) {
                    if (kind === 'payment') {
                        if (status === 'paid') return 'bg-green-100 text-green-700';
                        if (status === 'partial') return 'bg-yellow-100 text-yellow-700';
                        return 'bg-red-100 text-red-700';
                    }
                    if (kind === 'rental') {
                        if (status === 'returned') {
                            if (this.rental.fine_status === 'unpaid' || this.rental.fine_status === 'partial' || Number(this.rental.fine_amount || 0) > Number(this.rental.fine_paid_amount || 0)) return 'bg-amber-100 text-amber-700';
                            if (this.rental.payment_status !== 'paid' || !['none', 'paid'].includes(this.rental.fine_status)) return 'bg-amber-100 text-amber-700';
                            return 'bg-green-100 text-green-700';
                        }
                        if (status === 'overdue') return 'bg-red-100 text-red-700';
                        if (status === 'cancelled') return 'bg-slate-100 text-slate-700';
                        if (status === 'active') return 'bg-blue-100 text-blue-700';
                        if (status === 'waiting') return 'bg-blue-100 text-blue-700';
                    }
                    return 'bg-slate-100 text-slate-700';
                },

                statusBadgeText(status, kind) {
                    if (kind === 'payment') {
                        if (status === 'paid') return 'Lunas';
                        if (status === 'partial') return 'Sebagian';
                        return 'Belum Bayar';
                    }
                    if (kind === 'rental') {
                        if (status === 'waiting') return 'Booking';
                        if (status === 'active') return 'Sedang Disewa';
                        if (status === 'overdue') return 'Terlambat';
                        if (status === 'returned') {
                            if (this.rental.fine_status === 'unpaid' || this.rental.fine_status === 'partial' || Number(this.rental.fine_amount || 0) > Number(this.rental.fine_paid_amount || 0)) return 'Denda belum lunas';
                            if (this.rental.payment_status !== 'paid' || !['none', 'paid'].includes(this.rental.fine_status)) return 'Belum lunas';
                            return 'Selesai';
                        }
                        if (status === 'cancelled') return 'Dibatalkan';
                    }
                    return status;
                },

                paymentStatusLabel(status) {
                    if (status === 'paid') return 'LUNAS';
                    if (status === 'partial') return 'SEBAGIAN';
                    return 'BELUM BAYAR';
                },

                paymentStatusClass(status) {
                    if (status === 'paid') return 'text-green-600';
                    if (status === 'partial') return 'text-amber-600';
                    return 'text-red-600';
                },

                fineStatusLabel(status) {
                    if (status === 'paid') return 'LUNAS';
                    if (status === 'partial') return 'SEBAGIAN';
                    if (status === 'unpaid') return 'BELUM DIBAYAR';
                    return '-';
                },

                fineStatusClass(status) {
                    if (status === 'paid') return 'text-green-600';
                    if (status === 'partial') return 'text-amber-600';
                    if (status === 'unpaid') return 'text-red-600';
                    return 'text-slate-500';
                },

                setItemCondition(itemId, condition) {
                    this.itemCondition[itemId] = condition;
                    if (!this.needsFee(condition)) {
                        this.itemDamageFees[itemId] = 0;
                        this.itemNotes[itemId] = '';
                    }
                    this.calculateTotalDenda();
                },

                getItemCondition(itemId) {
                    return this.itemCondition[itemId] || null;
                },

                itemConditionLabel(condition) {
                    switch (condition) {
                        case 'good': return 'Baik';
                        case 'rusak_ringan': return 'Rusak Ringan';
                        case 'rusak_berat': return 'Rusak Berat';
                        case 'hilang': return 'Hilang';
                        default: return 'Belum Dicek';
                    }
                },

                itemBadgeClass(condition) {
                    switch (condition) {
                        case 'good': return 'bg-green-100 text-green-700 border border-green-200';
                        case 'rusak_ringan':
                        case 'rusak_berat':
                            return 'bg-yellow-100 text-yellow-700 border border-yellow-200';
                        case 'hilang': return 'bg-red-100 text-red-700 border border-red-200';
                        default: return 'bg-blue-100 text-blue-700 border border-blue-200';
                    }
                },

                needsFee(condition) {
                    return condition === 'rusak_ringan' || condition === 'rusak_berat' || condition === 'hilang';
                },

                markAllGood() {
                    (this.rental.items || []).forEach(item => {
                        this.itemCondition[item.id] = 'good';
                        this.itemNotes[item.id] = '';
                        this.itemDamageFees[item.id] = 0;
                    });
                    this.calculateTotalDenda();
                },

                resetItems() {
                    (this.rental.items || []).forEach(item => {
                        this.itemCondition[item.id] = null;
                        this.itemNotes[item.id] = '';
                        this.itemDamageFees[item.id] = 0;
                    });
                    this.calculateTotalDenda();
                },

                calculateTotalDenda() {
                    this.totalDenda = (this.rental.items || []).reduce((total, item) => {
                        return total + Number(this.itemDamageFees[item.id] || 0);
                    }, 0);
                },

                get confirmDisabled() {
                    if (this.controlsDisabled) return true;
                    const missing = (this.rental.items || []).some(item => !this.getItemCondition(item.id));
                    if (missing) return true;
                    const missingFee = (this.rental.items || []).some(item => {
                        const cond = this.getItemCondition(item.id);
                        return this.needsFee(cond) && Number(this.itemDamageFees[item.id] || 0) <= 0;
                    });
                    return missingFee;
                },

                async confirmReturn() {
                    if (this.confirmDisabled) return;

                    const { isConfirmed } = await Swal.fire({
                        icon: 'question',
                        title: 'Konfirmasi Pengembalian',
                        text: 'Proses akan menyimpan kondisi barang, denda, dan memperbarui status rental.',
                        showCancelButton: true,
                        confirmButtonText: 'Konfirmasi',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#2563eb',
                    });

                    if (!isConfirmed) return;

                    try {
                        Swal.fire({
                            title: 'Memproses...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading(),
                        });

                        const payload = {
                            payment_status: this.paymentDraft || this.rental.payment_status,
                            items: (this.rental.items || []).map(item => ({
                                id: item.id,
                                condition: this.getItemCondition(item.id),
                                notes: (this.itemNotes[item.id] || '').trim(),
                                damage_fee: Number(this.itemDamageFees[item.id] || 0),
                            })),
                        };

const response = await fetch('{{ $hasRental ? route('rentals.confirm-return-ajax', $rental) : '' }}', {
                            method: 'PATCH',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            Swal.close();
                            await Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: data.message || 'Pengembalian tidak dapat diproses.',
                            });
                            return;
                        }

                        const previousItems = this.rental.items || [];

                        this.rental = {
                            ...this.rental,
                            ...data.rental,
                            customer: data.rental.customer || this.rental.customer,
                            branch: data.rental.branch || this.rental.branch,
                            guarantees: data.rental.guarantees || this.rental.guarantees,
                            payments: data.rental.payments || this.rental.payments,
                            activity_logs: data.rental.activity_logs || this.rental.activity_logs,
                            items: (data.rental.items || []).map(item => {
                                const existingItem = previousItems.find(i => i.id === item.id) || {};
                                return {
                                    ...existingItem,
                                    ...item,
                                    category_name: item.category_name || existingItem.category_name || '-',
                                    damage_fee: Number(item.damage_fee ?? existingItem.damage_fee ?? 0),
                                    price: Number(item.price ?? existingItem.price ?? 0),
                                    subtotal: Number(item.subtotal ?? existingItem.subtotal ?? 0),
                                };
                            }),
                        };

                        (this.rental.items || []).forEach(item => {
                            const cond = item.return_condition;
                            if (cond) {
                                if (cond === 'baik') {
                                    this.itemCondition[item.id] = 'good';
                                } else if (cond === 'hilang') {
                                    this.itemCondition[item.id] = 'hilang';
                                } else {
                                    this.itemCondition[item.id] = cond;
                                }
                            }
                            this.itemNotes[item.id] = item.return_notes || '';
                            this.itemDamageFees[item.id] = Number(item.damage_fee || 0);
                        });

                        this.controlsDisabled = true;
                        this.calculateTotalDenda();

                        Swal.close();
                        await Swal.fire({
                            icon: 'success',
                            title: 'Pengembalian Berhasil',
                            text: 'Stock bertambah dan timeline diperbarui.',
                            confirmButtonColor: '#2563eb',
                        });
                    } catch (error) {
                        console.error(error);
                        Swal.close();
                        await Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan',
                            text: 'Silakan coba beberapa saat lagi.',
                        });
                    }
                },
            }
        }
    </script>
@endsection
