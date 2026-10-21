@extends('Layouts.app')

@section('title', 'Detail Penyewaan - ' . $rental->invoice_number)
@section('page-title', 'Detail Penyewaan')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div
        x-data="returnOperationalDashboard()"
        x-init="init()"
        class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6"
    >
        <div class="rounded-[20px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Detail Penyewaan</p>
                        <h1 class="text-2xl font-bold text-slate-900">Invoice <span x-text="rental.invoice_number"></span></h1>
                        <p class="mt-1 text-sm text-slate-500">Panel operasional pengembalian dengan tampilan modern SewaJas.</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold" :class="badgeClass(rental.rental_status, 'rental')" x-text="statusBadgeText(rental.rental_status, 'rental')"></span>
                    <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold" :class="badgeClass(rental.payment_status, 'payment')" x-text="statusBadgeText(rental.payment_status, 'payment')"></span>
                    <template x-if="rental.fine_status === 'unpaid' || rental.fine_status === 'partial'">
                        <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold bg-amber-100 text-amber-700">Ada Denda Belum Dibayar</span>
                    </template>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <a href="{{ route('rentals.index') }}" class="btn-secondary">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Kembali ke Penyewaan
                </a>
                @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminToko())
                @php
                    $isRentalLocked = in_array($rental->rental_status, ['active', 'overdue']) || $rental->payment_status === 'paid';
                @endphp
                <a href="{{ $isRentalLocked ? '#' : route('rentals.edit', $rental) }}"
                   class="btn-secondary {{ $isRentalLocked ? 'opacity-50 cursor-not-allowed' : '' }}"
                   @if($isRentalLocked) onclick="event.preventDefault(); return false;" @endif>
                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                    {{ $isRentalLocked ? 'Tidak Dapat Diedit' : 'Edit' }}
                </a>
                @endif
                <template x-if="rental.rental_status === 'waiting'">
                    <button type="button" @click="openHandoverModal()" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">
                        <i data-lucide="play" class="w-4 h-4"></i>
                        Mulai Sewa / Serah Terima
                    </button>
                </template>
                <a href="{{ route('rentals.receipt.show', $rental) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Receipt</a>
                <a href="{{ route('rentals.receipt.pdf', $rental) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Receipt PDF</a>
                <a href="javascript:void(0)" 
                   title="PDF akan otomatis terdownload — lampirkan manual di chat WhatsApp yang terbuka"
                   onclick="downloadPdfAndOpenWa('{{ route('rentals.receipt.pdf', $rental) }}', '{{ addslashes($rental->customer->name ?? 'Customer') }}', '{{ $rental->invoice_number }}', '{{ preg_replace('/[^0-9]/', '', str_starts_with($rental->customer->phone ?? '', '0') ? '62'.substr($rental->customer->phone, 1) : ($rental->customer->phone ?? '')) }}')"
                   class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">WA Receipt</a>
                <a href="{{ route('rentals.reminder', $rental) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Reminder</a>
                @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminToko())
                <button type="button" onclick="confirmDeleteRental('{{ $rental->invoice_number }}')" class="btn-secondary text-red-500 hover:bg-red-50">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Hapus
                </button>
                @endif
            </div>

            <!-- Hidden delete form for SweetAlert confirmation -->
            <form id="deleteRentalForm" action="{{ route('rentals.destroy', $rental) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>

            @push('scripts')
            <script>
            function confirmDeleteRental(invoiceNumber) {
                Swal.fire({
                    title: 'Hapus Penyewaan?',
                    html: 'Apakah Anda yakin ingin menghapus penyewaan <strong>' + invoiceNumber + '</strong>?<br>Semua data terkait akan ikut terhapus.<br>Tindakan ini tidak dapat dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteRentalForm').submit();
                    }
                });
            }

            function downloadPdfAndOpenWa(pdfUrl, customerName, invoiceNumber, customerPhone) {
                if (!customerPhone) {
                    alert('Nomor WhatsApp customer tidak tersedia.');
                    return;
                }

                var link = document.createElement('a');
                link.href = pdfUrl;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                var message = encodeURIComponent(
                    "Halo " + customerName + ",\\n\\n" +
                    "Berikut receipt sewa Anda " + invoiceNumber + ". " +
                    "Mohon cek file PDF yang baru saja di-download, lalu lampirkan di sini ya.\\n\\n" +
                    "Terima kasih."
                );

                setTimeout(function() {
                    window.open("https://wa.me/" + customerPhone + "?text=" + message, '_blank');
                }, 500);
            }
            </script>
            @endpush

            <div class="mt-8">
                <div class="relative flex items-center justify-between gap-2">
                    <div class="absolute left-0 right-0 top-4 h-1 rounded-full bg-slate-200"></div>
                    <div class="absolute left-0 top-4 h-1 rounded-full bg-blue-600 transition-all duration-300" :style="{ width: progressWidth + '%' }"></div>

                    <template x-for="step in progressSteps" :key="step.id">
                        <div class="relative z-10 flex flex-1 flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-semibold transition-all duration-300" :class="progressStepClass(step.id)">
                                <template x-if="currentStep > step.id">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </template>
                                <template x-if="currentStep <= step.id">
                                    <span x-text="step.id"></span>
                                </template>
                            </div>
                            <span class="mt-2 text-center text-xs font-medium text-slate-600" x-text="step.label"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">Informasi Rental</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pelanggan</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900" x-text="rental.customer.name"></p>
                            <p class="mt-1 text-sm text-slate-500" x-text="rental.customer.phone || '-'"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cabang</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900" x-text="rental.branch.name || '-'"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diserahkan oleh</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900" x-text="rental.created_by || '-'"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diproses Pengembalian oleh</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900" x-text="rental.returned_by || '-'"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Sewa</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900" x-text="rental.rental_date"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Deadline</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900" x-text="rental.return_due_date"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Invoice</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900 break-all" x-text="rental.invoice_number"></p>
                        </div>
                    </div>
                </div>

                <div x-show="rental.notes" x-transition class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">Catatan</h2>
                    </div>
                    <p class="text-sm text-slate-700 whitespace-pre-wrap" x-text="rental.notes"></p>
                </div>

                <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">Daftar Barang</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <template x-for="item in rental.items" :key="'item-card-' + item.id">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-blue-200 hover:bg-white">
                                <div class="flex gap-4">
                                    <img :src="item.photo || defaultImage" alt="Produk" class="h-20 w-20 rounded-2xl border border-slate-200 object-cover" x-on:error="$event.target.src = defaultImage">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-base font-semibold text-slate-900" x-text="item.product_name"></h3>
                                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kategori</p>
                                                <p class="mt-1 text-slate-700" x-text="item.category_name || '-'"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Size</p>
                                                <p class="mt-1 font-semibold text-slate-900" x-text="item.size || '-'"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga</p>
                                                <p class="mt-1 font-semibold text-slate-900" x-text="fmt(item.price)"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Item</p>
                                                <span class="mt-1 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="itemBadgeClass(getItemCondition(item.id))" x-text="itemConditionLabel(getItemCondition(item.id))"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-if="showOperationalPanel">
                    <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <h2 class="text-lg font-bold text-slate-900">Panel Operasional Pengembalian</h2>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="controlsDisabled ? 'bg-slate-100 text-slate-600' : 'bg-blue-100 text-blue-700'" x-text="controlsDisabled ? 'Read Only' : 'Aktif'"></span>
                        </div>

                        <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <button type="button" @click="markAllGood()" :disabled="controlsDisabled" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <span>✓</span>
                                <span>Tandai Semua Baik</span>
                            </button>
                            <button type="button" @click="resetItems()" :disabled="controlsDisabled" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <span>↺</span>
                                <span>Reset</span>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <template x-for="item in rental.items" :key="'inspection-' + item.id">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 class="text-base font-semibold text-slate-900" x-text="item.product_name"></h3>
                                            <p class="text-sm text-slate-500">Ukuran <span x-text="item.size || '-'"></span></p>
                                        </div>
                                        <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold" :class="itemBadgeClass(getItemCondition(item.id))" x-text="itemConditionLabel(getItemCondition(item.id))"></span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                        <button type="button" @click="setItemCondition(item.id, 'good')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'good' ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700'">Baik</button>
                                        <button type="button" @click="setItemCondition(item.id, 'rusak_ringan')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'rusak_ringan' || getItemCondition(item.id) === 'rusak_berat' ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700'">Rusak</button>
                                        <button type="button" @click="setItemCondition(item.id, 'lost')" :disabled="controlsDisabled" class="rounded-2xl border-2 px-4 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" :class="getItemCondition(item.id) === 'lost' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700'">Hilang</button>
                                    </div>

                                    <div x-show="needsFee(getItemCondition(item.id))" x-transition class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Denda</label>
                                            <div class="relative">
                                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-500">Rp</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    x-model="itemDamageFees[item.id]"
                                                    @input="calculateTotalDenda()"
                                                    :disabled="controlsDisabled"
                                                    class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-12 pr-4 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                                    placeholder="Masukkan nominal denda"
                                                >
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
                                            <textarea
                                                x-model="itemNotes[item.id]"
                                                :disabled="controlsDisabled"
                                                rows="3"
                                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                                placeholder="Catatan kondisi barang"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-6">
                            <button
                                type="button"
                                @click="confirmReturn()"
                                :disabled="confirmDisabled"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-4 text-base font-bold text-white shadow-sm transition"
                                :class="confirmDisabled ? 'bg-slate-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span x-text="controlsDisabled ? 'Pengembalian Sudah Diproses' : 'Konfirmasi Pengembalian'"></span>
                            </button>
                            <p class="mt-3 text-center text-xs text-slate-500">Submit dinonaktifkan jika ada item belum dipilih atau nominal denda wajib belum diisi.</p>
                        </div>
                    </div>
                </template>

                <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">Riwayat Pembayaran</h2>
                    </div>

                    <div class="space-y-3" x-show="rental.payments.length > 0">
                        <template x-for="payment in rental.payments" :key="'payment-' + payment.id">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900" x-text="payment.payment_number || '-'"></p>
                                        <p class="mt-1 text-xs text-slate-500" x-text="payment.paid_at || '-'"></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-slate-900" x-text="fmt(payment.amount)"></p>
                                        <p class="mt-1 text-xs text-slate-500" x-text="payment.method_label || payment.method || '-'"></p>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-slate-500" x-show="payment.notes" x-text="payment.notes"></p>
                            </div>
                        </template>
                    </div>
                    <div x-show="rental.payments.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Belum ada pembayaran.
                    </div>
                </div>

                <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h2 class="text-lg font-bold text-slate-900">Aktivitas</h2>
                            </div>
                            <a href="{{ route('rentals.activity-log.download', $rental) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download Log
                            </a>
                        </div>
                    </div>

                    <div class="space-y-3" x-show="rental.activity_logs.length > 0">
                        <template x-for="log in rental.activity_logs" :key="'log-' + log.id">
                            <div class="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-800" x-text="log.description"></p>
                                    <p class="mt-1 text-xs text-slate-500"><span x-text="log.created_at || '-'"></span> • <span x-text="log.user || '-'"></span></p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="rental.activity_logs.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Belum ada aktivitas.
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">Informasi Jaminan</h2>
                    </div>

                    <div class="space-y-3" x-show="rental.guarantees.length > 0">
                        <template x-for="guarantee in rental.guarantees" :key="'guarantee-' + guarantee.id">
<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Jaminan</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900" x-text="guarantee.type_label || guarantee.type || '-'"></p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="guarantee.status === 'returned' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'" x-text="guarantee.status === 'returned' ? 'Dikembalikan' : 'Ditahan'"></span>
                                </div>
                                <div class="mt-3 space-y-2 text-sm">
                                    <template x-if="guarantee.id_number">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-slate-500">Nomor</span>
                                            <span class="font-semibold text-slate-900" x-text="guarantee.id_number"></span>
                                        </div>
                                    </template>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-slate-500">Deposit</span>
                                        <span class="font-semibold text-slate-900" x-text="fmt(guarantee.deposit_amount)"></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3" x-show="guarantee.notes">
                                        <span class="text-slate-500">Catatan</span>
                                        <span class="font-semibold text-slate-900 text-right" x-text="guarantee.notes"></span>
                                    </div>
<div x-show="guarantee.id_photo_url" class="mt-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Foto Identitas</p>
                                        <img :src="guarantee.id_photo_url" alt="Foto Identitas" class="h-28 w-auto rounded-lg border border-slate-200 object-cover cursor-pointer" @click="openPhotoModal(guarantee.id_photo_url)">
                                    </div>
                            </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="rental.guarantees.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Tidak ada jaminan.
                    </div>
                </div>

                <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">Ringkasan Pembayaran</h2>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Subtotal</span>
                            <span class="font-semibold text-slate-900" x-text="fmt(rental.subtotal)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Deposit</span>
                            <span class="font-semibold text-slate-900" x-text="fmt(rental.deposit)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Diskon</span>
                            <span class="font-semibold text-slate-900" x-text="fmt(rental.discount)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Sewa (tagihan)</span>
                            <span class="font-semibold text-slate-900" x-text="fmt(rental.total_amount)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm" x-show="rental.fine_amount > 0">
                            <span class="text-slate-500">Denda</span>
                            <span class="font-semibold text-red-600" x-text="fmt(rental.fine_amount)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm" x-show="rental.total_amount > 0">
                            <span class="text-slate-500">Status Sewa</span>
                            <span class="font-semibold" :class="paymentStatusClass(rental.payment_status)" x-text="paymentStatusLabel(rental.payment_status)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm" x-show="rental.fine_amount > 0">
                            <span class="text-slate-500">Status Denda</span>
                            <span class="font-semibold" :class="fineStatusClass(rental.fine_status)" x-text="fineStatusLabel(rental.fine_status)"></span>
                        </div>
                        <template x-if="rental.change_amount > 0">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Kembalian</span>
                                <span class="font-semibold" :class="rental.refund_given > 0 ? 'text-green-600' : 'text-amber-600'" x-text="rental.refund_given > 0 ? 'Kembalian sudah diberikan: ' + fmt(rental.refund_given) : 'Kembalian harus diberikan: ' + fmt(rental.change_amount)"></span>
                            </div>
                        </template>
                        <template x-if="rental.change_amount === 0 && (rental.fine_status === 'unpaid' || rental.fine_status === 'partial')">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Sisa Denda Belum Tertutup</span>
                                <span class="font-semibold text-red-600" x-text="fmt(Math.max(0, (Number(rental.fine_amount) || 0) - (Number(rental.fine_paid_amount) || 0)))"></span>
                            </div>
                        </template>
                        <div class="border-t border-slate-200 pt-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold uppercase tracking-wide text-slate-500">Total Tagihan</span>
                                <span class="text-3xl font-extrabold text-blue-700" x-text="fmt(displayGrandTotal)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <template x-if="rental.change_amount > 0 && rental.refund_given === 0">
                    <div class="rounded-[20px] border border-amber-200 bg-amber-50 p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <div class="rounded-xl bg-amber-100 p-2 text-amber-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-lg font-bold text-amber-700">Kembalian Pelanggan</h2>
                        </div>
                        <p class="text-sm text-amber-700 mb-4">Ada kelebihan pembayaran sebesar <span class="font-bold" x-text="fmt(rental.change_amount)"></span>. Jangan lupa memberikan kembalian kepada pelanggan.</p>
                        <button type="button" @click="openRefundModal()" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-amber-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Tandai Kembalian Sudah Diberikan
                        </button>
                    </div>
                </template>

                @if ($rental->rental_status != 'cancelled' && ($rental->payment_status !== 'paid' || ($rental->fine_status !== 'paid' && $rental->fine_status !== 'none')))
                    <div class="rounded-[20px] border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2v6a2 2v6h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2v6a2 2v6"></path>
                                </svg>
                            </div>
                            <h2 class="text-lg font-bold text-slate-900">Input Pembayaran</h2>
                        </div>

                        <form method="POST" action="{{ route('rentals.payment', $rental) }}" class="space-y-3" x-on:submit="preparePaymentSubmit($event)">
                            @csrf
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tipe Pembayaran</label>
                                <select name="payment_type" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="rental">Sewa</option>
                                    <option value="late_fee">Denda Telat</option>
                                    <option value="damage_fee">Denda Kerusakan</option>
                                    <option value="deposit">Deposit</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah</label>
                                <input type="text" name="amount" id="payment_amount" placeholder="Masukkan jumlah pembayaran" required 
                                       class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                       x-on:input="formatPaymentAmount($event)">
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Metode</label>
                                <select name="method" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" x-model="paymentMethod">
                                    <option value="cash">Tunai</option>
                                    <option value="transfer">Transfer</option>
                                    @if($qris['available'])
                                    <option value="qris">QRIS</option>
                                    @endif
                                </select>
                                <div x-show="paymentMethod === 'qris'" x-transition class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-center">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Scan QR code di bawah untuk membayar via QRIS</p>
                                    @if($qris['url'])
                                        <img src="{{ $qris['url'] }}" alt="QRIS Payment" class="mx-auto h-48 w-48 rounded-xl border border-slate-100 object-contain">
                                    @else
                                        <p class="text-sm text-slate-500">QRIS belum diupload di Pengaturan.</p>
                                    @endif
                                </div>
                            </div>
                            <button type="submit" :disabled="paymentProcessing" class="inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed">
                                <span x-show="paymentProcessing">Memproses...</span>
                                <span x-show="!paymentProcessing">Proses Pembayaran</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Modal Konfirmasi Serah Terima -->
            <div x-show="showHandoverModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.stop>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="rounded-xl bg-emerald-50 p-2 text-emerald-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">Konfirmasi Serah Terima</h3>
                    </div>

                    <p class="text-sm text-slate-600 mb-4">Pastikan jas sudah benar-benar diserahkan kepada customer.</p>

                    <div class="space-y-3 mb-6">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Informasi Rental</p>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-slate-500">Invoice:</span>
                                    <span class="ml-2 font-semibold text-slate-900" x-text="rental.invoice_number"></span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Pelanggan:</span>
                                    <span class="ml-2 font-semibold text-slate-900" x-text="rental.customer.name"></span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Status Pembayaran:</span>
                                    <span class="ml-2 font-semibold" :class="rental.payment_status === 'paid' ? 'text-green-700' : (rental.payment_status === 'partial' ? 'text-amber-700' : 'text-slate-500')" x-text="rental.payment_status === 'paid' ? 'Lunas' : (rental.payment_status === 'partial' ? 'Sebagian' : 'Belum Bayar')"></span>
                                    <template x-if="rental.fine_status === 'unpaid' || rental.fine_status === 'partial'">
                                        <span class="ml-2 text-xs font-semibold text-red-600">(Ada Denda Belum Dibayar)</span>
                                    </template>
                                </div>
                                <div>
                                    <span class="text-slate-500">Jaminan:</span>
                                    <span class="ml-2 font-semibold" :class="(rental.guarantees || []).some(g => g.status === 'held') ? 'text-blue-700' : ((rental.guarantees || []).length > 0 ? 'text-green-700' : 'text-slate-500')" x-text="(rental.guarantees || []).some(g => g.status === 'held') ? 'Ditahan' : ((rental.guarantees || []).length > 0 ? 'Dikembalikan' : 'Belum Ada')"></span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-slate-500">Tanggal Kembali:</span>
                                    <span class="ml-2 font-semibold text-slate-900" x-text="rental.return_due_date"></span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Produk yang Disewa</p>
                            <div class="space-y-1">
                                <template x-for="item in rental.items" :key="item.id">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-slate-900" x-text="item.product_name"></span>
                                        <span class="text-slate-500">x<span x-text="item.quantity"></span></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="showHandoverModal = false" :disabled="handoverLoading" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60">
                            Batal
                        </button>
                        <button type="button" @click="confirmHandover()" :disabled="handoverLoading" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                            <span x-show="!handoverLoading">Konfirmasi Serah Terima</span>
                            <span x-show="handoverLoading">Memproses...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Konfirmasi Kembalian -->
            <div x-show="showRefundModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.stop>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="rounded-xl bg-amber-50 p-2 text-amber-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">Konfirmasi Kembalian</h3>
                    </div>

                    <p class="text-sm text-slate-600 mb-4">Anda akan mencatat pembayaran kembalian sebesar <span class="font-bold" x-text="fmt(rental.change_amount)"></span> untuk invoice <span x-text="rental.invoice_number"></span>.</p>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-6">
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-slate-500">Invoice:</span>
                                <span class="ml-2 font-semibold text-slate-900" x-text="rental.invoice_number"></span>
                            </div>
                            <div>
                                <span class="text-slate-500">Customer:</span>
                                <span class="ml-2 font-semibold text-slate-900" x-text="rental.customer.name"></span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-slate-500">Jumlah Kembalian:</span>
                                <span class="ml-2 font-bold text-amber-700" x-text="fmt(rental.change_amount)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="showRefundModal = false" :disabled="refundLoading" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60">
                            Batal
                        </button>
                        <button type="button" @click="confirmRefund()" :disabled="refundLoading" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-60">
                            <span x-show="!refundLoading">Ya, Beri Kembalian</span>
                            <span x-show="refundLoading">Memproses...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function returnOperationalDashboard() {
            return {
                defaultImage: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" fill="%23f1f5f9" rx="16"/><g transform="translate(20, 18)" fill="none" stroke="%2394a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4h10l10 10h4l-8 8v6h-24v-6z"/><path d="M10 14h24"/><path d="M14 14v6"/><path d="M34 14v6"/><circle cx="20" cy="28" r="2"/><circle cx="28" cy="28" r="2"/></g></svg>',
                controlsDisabled: false,
                showHandoverModal: false,
                handoverLoading: false,
                showRefundModal: false,
                refundLoading: false,
                paymentProcessing: false,

                progressSteps: [
                    { id: 1, label: 'Booking' },
                    { id: 2, label: 'Disewa' },
                    { id: 3, label: 'Dikembalikan' },
                    { id: 4, label: 'Selesai' },
                ],

                rental: @json($rentalData),
                paymentDraft: null,
                paymentMethod: '{{ $rental->payment_method ?? '' }}',
                itemCondition: {},
                itemNotes: {},
                itemDamageFees: {},

displayedPhotoModal: null,

                init() {
                    (this.rental.items || []).forEach((item) => {
                        const condition = item.return_condition;

                        if (condition === 'baik') {
                            this.itemCondition[item.id] = 'good';
                        } else if (condition === 'hilang') {
                            this.itemCondition[item.id] = 'lost';
                        } else if (condition) {
                            this.itemCondition[item.id] = condition;
                        } else {
                            this.itemCondition[item.id] = null;
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
                },

                get showOperationalPanel() {
                    return ['active', 'overdue', 'returned'].includes(this.rental.rental_status);
                },

                get currentStep() {
                    if (this.rental.rental_status === 'returned') return 4;
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
                    return Number(this.rental.fine_amount || 0);
                },

                get displayGrandTotal() {
                    return Number(this.rental.total_amount || 0) + Number(this.rental.fine_amount || 0);
                },

                progressStepClass(step) {
                    if (this.currentStep >= step) {
                        return 'border-blue-600 bg-blue-600 text-white';
                    }

                    return 'border-slate-300 bg-white text-slate-400';
                },

                fmt(value) {
                    const amount = Number(value || 0);
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
                },

                formatPaymentAmount(event) {
                    // Get the input element
                    const input = event.target;
                    // Get the current value without any non-digit characters
                    let value = input.value.replace(/[^0-9]/g, '');
                    // Format with thousand separators
                    input.value = value ? new Intl.NumberFormat('id-ID').format(Number(value)) : '';
                },

                preparePaymentSubmit(event) {
                    if (this.paymentProcessing) {
                        event.preventDefault();
                        return false;
                    }
                    this.paymentProcessing = true;
                    const input = document.getElementById('payment_amount');
                    if (input) {
                        input.value = input.value.replace(/[^0-9]/g, '');
                    }
                },

                badgeClass(status, kind) {
                    if (kind === 'payment') {
                        if (status === 'paid') return 'bg-green-100 text-green-700';
                        if (status === 'partial') return 'bg-amber-100 text-amber-700';
                        return 'bg-red-100 text-red-700';
                    }

                    if (status === 'returned') return 'bg-green-100 text-green-700';
                    if (status === 'overdue') return 'bg-red-100 text-red-700';
                    if (status === 'cancelled') return 'bg-slate-200 text-slate-700';
                    if (status === 'active') return 'bg-blue-100 text-blue-700';
                    return 'bg-amber-100 text-amber-700';
                },

                statusBadgeText(status, kind) {
                    if (kind === 'payment') {
                        if (status === 'paid') return 'Lunas';
                        if (status === 'partial') return 'Sebagian';
                        return 'Belum Bayar';
                    }

                    if (status === 'waiting') return 'Booking';
                    if (status === 'active') return 'Sedang Disewa';
                    if (status === 'overdue') return 'Terlambat';
                    if (status === 'returned') return 'Selesai';
                    if (status === 'cancelled') return 'Dibatalkan';
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
                    if (condition === 'good') return 'Baik';
                    if (condition === 'rusak_ringan' || condition === 'rusak_berat') return 'Rusak';
                    if (condition === 'lost') return 'Hilang';
                    return 'Belum Dicek';
                },

                itemBadgeClass(condition) {
                    if (condition === 'good') return 'bg-green-100 text-green-700';
                    if (condition === 'rusak_ringan' || condition === 'rusak_berat') return 'bg-amber-100 text-amber-700';
                    if (condition === 'lost') return 'bg-red-100 text-red-700';
                    return 'bg-slate-200 text-slate-700';
                },

                needsFee(condition) {
                    return condition === 'rusak_ringan' || condition === 'rusak_berat' || condition === 'lost';
                },

                markAllGood() {
                    (this.rental.items || []).forEach((item) => {
                        this.itemCondition[item.id] = 'good';
                        this.itemNotes[item.id] = '';
                        this.itemDamageFees[item.id] = 0;
                    });

                    this.calculateTotalDenda();
                },

                resetItems() {
                    (this.rental.items || []).forEach((item) => {
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

                    const hasMissingCondition = (this.rental.items || []).some((item) => !this.getItemCondition(item.id));
                    if (hasMissingCondition) return true;

                    const hasMissingFee = (this.rental.items || []).some((item) => {
                        const condition = this.getItemCondition(item.id);
                        return this.needsFee(condition) && Number(this.itemDamageFees[item.id] || 0) <= 0;
                    });

                    return hasMissingFee;
                },

                async confirmReturn() {
                    if (this.confirmDisabled) return;

                    const confirmation = await Swal.fire({
                        icon: 'question',
                        title: 'Konfirmasi pengembalian?',
                        text: 'Proses akan menyimpan kondisi barang, denda, dan memperbarui status rental.',
                        showCancelButton: true,
                        confirmButtonText: 'Konfirmasi',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#2563EB',
                    });

                    if (!confirmation.isConfirmed) return;

                    try {
                        Swal.fire({
                            title: 'Memproses...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading(),
                        });

                        const payload = {
                            payment_status: this.paymentDraft,
                            items: (this.rental.items || []).map((item) => ({
                                id: item.id,
                                condition: this.getItemCondition(item.id),
                                notes: (this.itemNotes[item.id] || '').trim(),
                                damage_fee: Number(this.itemDamageFees[item.id] || 0),
                            })),
                        };

                        const response = await fetch('{{ route('rentals.confirm-return-ajax', $rental) }}', {
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
                            items: (data.rental.items || []).map((item) => {
                                const existingItem = previousItems.find((currentItem) => currentItem.id === item.id) || {};

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

                        (this.rental.items || []).forEach((item) => {
                            const condition = item.return_condition;

                            if (condition === 'baik') {
                                this.itemCondition[item.id] = 'good';
                            } else if (condition === 'hilang') {
                                this.itemCondition[item.id] = 'lost';
                            } else {
                                this.itemCondition[item.id] = condition;
                            }

                            this.itemNotes[item.id] = item.return_notes || '';
                            this.itemDamageFees[item.id] = Number(item.damage_fee || 0);
                        });

                        this.controlsDisabled = true;
                        this.calculateTotalDenda();

                        Swal.close();
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Pengembalian berhasil diproses.',
                            confirmButtonColor: '#2563EB',
                        });
                    } catch (error) {
                        Swal.close();
                        await Swal.fire({
                            icon: 'error',
                            title: 'Terjadi kesalahan',
                            text: 'Silakan coba beberapa saat lagi.',
                        });
                        console.error(error);
                    }
                },

                openHandoverModal() {
                    this.showHandoverModal = true;
                },

                async confirmHandover() {
                    if (this.handoverLoading) return;

                    const confirmation = await Swal.fire({
                        icon: 'question',
                        title: 'Konfirmasi serah terima?',
                        text: 'Pastikan jas sudah benar-benar diserahkan kepada customer.',
                        showCancelButton: true,
                        confirmButtonText: 'Konfirmasi',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#059669',
                    });

                    if (!confirmation.isConfirmed) return;

                    this.handoverLoading = true;

                    try {
                        const response = await fetch('{{ route('rentals.handover', $rental) }}', {
                            method: 'PATCH',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            },
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Serah terima gagal.');
                        }

                        this.rental = {
                            ...this.rental,
                            ...data.rental,
                            customer: data.rental.customer || this.rental.customer,
                            branch: data.rental.branch || this.rental.branch,
                            guarantees: data.rental.guarantees || this.rental.guarantees,
                            payments: data.rental.payments || this.rental.payments,
                            activity_logs: data.rental.activity_logs || this.rental.activity_logs,
                            items: (data.rental.items || []).map((item) => {
                                const existingItem = (this.rental.items || []).find((currentItem) => currentItem.id === item.id) || {};
                                return {
                                    ...existingItem,
                                    ...item,
                                    category_name: item.category_name || existingItem.category_name || '-',
                                    damage_fee: Number(item.damage_fee ?? existingItem.damage_fee ?? 0),
                                    price: Number(item.price ?? existingItem.price ?? 0),
                                };
                            }),
                        };

                        this.showHandoverModal = false;

                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: data.message || 'Serah terima berhasil!',
                            confirmButtonColor: '#059669',
                        });
                    } catch (error) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: error.message || 'Serah terima tidak dapat diproses.',
                            confirmButtonColor: '#2563EB',
                        });
                    } finally {
                        this.handoverLoading = false;
                    }
                },

                openRefundModal() {
                    this.refundLoading = false;
                    this.showRefundModal = true;
                },

                async confirmRefund() {
                    if (this.refundLoading) return;

                    const confirmation = await Swal.fire({
                        icon: 'question',
                        title: 'Konfirmasi pembayaran kembalian?',
                        text: 'Anda akan mencatat kembalian sebesar ' + this.fmt(this.rental.change_amount) + ' untuk ' + this.rental.invoice_number + '.',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Beri Kembalian',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#D97706',
                    });

                    if (!confirmation.isConfirmed) return;

                    this.refundLoading = true;

                    try {
                        const response = await fetch('{{ route('rentals.mark-refund-given', $rental) }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                amount: this.rental.change_amount,
                                notes: 'Kembalian overpayment',
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Pencatatan kembalian gagal.');
                        }

                        this.rental = {
                            ...this.rental,
                            ...data.rental,
                            customer: data.rental.customer || this.rental.customer,
                            branch: data.rental.branch || this.rental.branch,
                            guarantees: data.rental.guarantees || this.rental.guarantees,
                            payments: data.rental.payments || this.rental.payments,
                            activity_logs: data.rental.activity_logs || this.rental.activity_logs,
                            items: (data.rental.items || []).map((item) => {
                                const existingItem = (this.rental.items || []).find((currentItem) => currentItem.id === item.id) || {};
                                return {
                                    ...existingItem,
                                    ...item,
                                    category_name: item.category_name || existingItem.category_name || '-',
                                    damage_fee: Number(item.damage_fee ?? existingItem.damage_fee ?? 0),
                                    price: Number(item.price ?? existingItem.price ?? 0),
                                };
                            }),
                            change_amount: Number(data.rental.change_amount ?? this.rental.change_amount ?? 0),
                            overpayment: Number(data.rental.change_amount ?? this.rental.change_amount ?? 0),
                            refund_given: Number(data.rental.refund_given ?? this.rental.refund_given ?? 0),
                        };

                        this.showRefundModal = false;

                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: data.message || 'Kembalian berhasil dicatat.',
                            confirmButtonColor: '#D97706',
                        });
                    } catch (error) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: error.message || 'Pencatatan kembalian tidak dapat diproses.',
                            confirmButtonColor: '#DC2626',
                        });
                    } finally {
                        this.refundLoading = false;
                    }
                },
            };
        }
    </script>
@endsection
