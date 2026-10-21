@extends('Layouts.app')

@section('title', 'Detail Riwayat Penyewaan - ' . $history->invoice_number)
@section('page-title', 'Detail Riwayat Penyewaan')

@section('content')
    @php
        $money = fn ($amount) => 'Rp ' . number_format((float) ($amount ?? 0), 0, ',', '.');
        $items = $history->items_snapshot ?? [];
        $payments = $history->payments_snapshot ?? [];
        $guarantees = $history->guarantees_snapshot ?? [];
        $grandTotal = (float) $history->total_amount + (float) $history->fine_amount;
        $totalPaid = (float) $history->paid_amount + (float) $history->fine_paid_amount;
    @endphp

    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-3 py-5 sm:px-5 sm:py-7">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('rental-histories.index') }}" class="btn-secondary w-fit">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Kembali ke Riwayat
            </a>
            <div class="flex flex-wrap gap-2">
                <a href="javascript:void(0)"
                   title="PDF akan otomatis terdownload — lampirkan manual di chat WhatsApp yang terbuka"
                   onclick="downloadInvoiceAndOpenWa('{{ route('rental-histories.invoice', $history) }}', '{{ addslashes($history->customer->name ?? 'Pelanggan') }}', '{{ $history->invoice_number }}', '{{ preg_replace('/[^0-9]/', '', str_starts_with($history->customer->phone ?? '', '0') ? '62'.substr($history->customer->phone, 1) : ($history->customer->phone ?? '')) }}')"
                   class="btn-secondary w-fit">WA Invoice</a>
                <a href="{{ route('rental-histories.invoice', $history) }}" class="btn-primary w-fit">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    Unduh Invoice PDF
                </a>
            </div>
        </div>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white px-6 py-6 sm:px-9 sm:py-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-700">Invoice Penyewaan</p>
                        <h1 class="mt-2 break-all font-mono text-xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                            {{ $history->invoice_number }}
                        </h1>
                        <p class="mt-1.5 text-sm text-slate-500">
                            Selesai pada {{ $history->completed_at?->format('d M Y, H:i') ?? '-' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-emerald-700">
                            Penyewaan selesai
                        </span>
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-blue-700">
                            Pembayaran {{ $history->payment_status_label }}
                        </span>
                    </div>
                </div>
            </header>

            <div class="px-6 sm:px-9">
                <section class="grid gap-6 border-b border-dashed border-slate-200 py-6 sm:grid-cols-2 sm:py-7">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Penyewa</h2>
                        <p class="mt-2 break-words text-base font-bold text-slate-900">{{ $history->customer->name ?? 'Pelanggan tidak tersedia' }}</p>
                        <p class="mt-1 text-sm text-slate-500">Telepon: {{ $history->customer->phone ?? '-' }}</p>
                    </div>
                    <div class="sm:border-l sm:border-slate-100 sm:pl-8">
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Cabang</h2>
                        <p class="mt-2 break-words text-base font-bold text-slate-900">{{ $history->branch->name ?? '-' }}</p>
                        <p class="mt-1 text-sm text-slate-500">Petugas penyewaan: {{ $history->createdBy->name ?? '-' }}</p>
                    </div>
                </section>

                <section class="grid gap-x-8 border-b border-dashed border-slate-200 py-5 sm:grid-cols-2 lg:grid-cols-3 lg:py-6">
                    <div class="border-b border-slate-100 py-3 sm:py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal sewa</p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $history->rental_date?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="border-b border-slate-100 py-3 sm:py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jatuh tempo</p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $history->return_due_date?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="border-b border-slate-100 py-3 sm:py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dikembalikan</p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $history->actual_return_date?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="border-b border-slate-100 py-3 sm:py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Durasi</p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $history->duration_days }} hari</p>
                    </div>
                    <div class="border-b border-slate-100 py-3 sm:py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dikembalikan oleh</p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $history->returnedBy->name ?? '-' }}</p>
                    </div>
                    <div class="border-b border-slate-100 py-3 sm:py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal invoice</p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $history->completed_at?->format('d M Y') ?? '-' }}</p>
                    </div>
                </section>

                <section class="py-6 sm:py-7">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Rincian penyewaan</h2>
                            <p class="mt-1 text-xs text-slate-500">Informasi produk disimpan sesuai snapshot saat transaksi.</p>
                        </div>
                        <span class="text-xs font-semibold text-slate-500">{{ count($items) }} item</span>
                    </div>
                    @if(count($items))
                        <div class="-mx-2 overflow-x-auto px-2">
                            <table class="w-full min-w-[720px] table-fixed text-left text-sm">
                                <thead>
                                    <tr class="border-y border-slate-200 bg-slate-50/70 text-[10px] uppercase tracking-wide text-slate-500">
                                        <th class="w-[34%] px-3 py-3 font-semibold">Produk</th>
                                        <th class="w-[10%] px-2 py-3 font-semibold">Ukuran</th>
                                        <th class="w-[9%] px-2 py-3 text-center font-semibold">Qty</th>
                                        <th class="w-[19%] px-2 py-3 text-right font-semibold">Harga/hari</th>
                                        <th class="w-[12%] px-2 py-3 text-center font-semibold">Durasi</th>
                                        <th class="w-[20%] px-3 py-3 text-right font-semibold">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($items as $item)
                                        <tr class="align-top">
                                            <td class="break-words px-3 py-4">
                                                <p class="font-semibold leading-5 text-slate-800">{{ $item['product_name'] ?? 'Produk' }}</p>
                                                @if(!empty($item['product_snapshot']['code']))
                                                    <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $item['product_snapshot']['code'] }}</p>
                                                @endif
                                            </td>
                                            <td class="px-2 py-4 text-slate-600">{{ $item['product_size'] ?? '-' }}</td>
                                            <td class="px-2 py-4 text-center text-slate-600">{{ $item['quantity'] ?? 0 }}</td>
                                            <td class="px-2 py-4 text-right text-slate-600">{{ $money($item['price_per_day'] ?? 0) }}</td>
                                            <td class="px-2 py-4 text-center text-slate-600">{{ $item['duration_days'] ?? $history->duration_days }} hari</td>
                                            <td class="px-3 py-4 text-right font-semibold text-slate-800">{{ $money($item['subtotal'] ?? 0) }}</td>
                                        </tr>
                                        @if(!empty($item['return_condition']) || !empty($item['return_notes']) || (float) ($item['damage_fee'] ?? 0) > 0)
                                            <tr>
                                                <td colspan="6" class="px-3 pb-4 pt-0 text-xs leading-5 text-slate-500">
                                                    Kondisi kembali: {{ ucfirst(str_replace('_', ' ', $item['return_condition'] ?? '-')) }}
                                                    @if(!empty($item['return_notes'])) · {{ $item['return_notes'] }} @endif
                                                    @if((float) ($item['damage_fee'] ?? 0) > 0)
                                                        <span class="font-semibold text-rose-600"> · Denda kerusakan {{ $money($item['damage_fee']) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="border-y border-slate-200 py-5 text-sm text-slate-500">Tidak ada snapshot item pada transaksi ini.</p>
                    @endif
                </section>

                <section class="grid gap-7 border-t border-dashed border-slate-200 py-6 sm:py-7 lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-10">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Riwayat pembayaran</h2>
                        @if(count($payments))
                            <div class="mt-3 divide-y divide-slate-100 border-y border-slate-100">
                                @foreach($payments as $payment)
                                    <div class="flex items-center justify-between gap-4 py-3.5">
                                        <div class="min-w-0">
                                            <p class="break-all font-mono text-xs font-semibold text-slate-700">{{ $payment['payment_number'] ?? 'Pembayaran' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ $payment['method_label'] ?? ucfirst($payment['method'] ?? '-') }}
                                                · {{ ucfirst(str_replace('_', ' ', $payment['type'] ?? 'rental')) }}
                                                · {{ !empty($payment['paid_at']) ? \Carbon\Carbon::parse($payment['paid_at'])->format('d M Y, H:i') : '-' }}
                                            </p>
                                        </div>
                                        <p class="shrink-0 text-right text-sm font-bold {{ (float) ($payment['amount'] ?? 0) < 0 ? 'text-rose-600' : 'text-slate-800' }}">
                                            {{ (float) ($payment['amount'] ?? 0) < 0 ? '−' : '' }}{{ $money(abs((float) ($payment['amount'] ?? 0))) }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-3 text-sm text-slate-500">Tidak ada catatan pembayaran.</p>
                        @endif
                    </div>

                    <div class="self-start rounded-2xl border border-slate-200 bg-slate-50/70 p-5 sm:p-6">
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Ringkasan tagihan</h2>
                        <dl class="mt-4 space-y-3.5 text-sm">
                            <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Subtotal</dt><dd class="shrink-0 text-right font-medium text-slate-800">{{ $money($history->subtotal) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Diskon</dt><dd class="shrink-0 text-right font-medium text-slate-800">−{{ $money($history->discount) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Total sewa</dt><dd class="shrink-0 text-right font-medium text-slate-800">{{ $money($history->total_amount) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Denda telat</dt><dd class="shrink-0 text-right font-medium text-slate-800">{{ $money($history->late_fee) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Total denda</dt><dd class="shrink-0 text-right font-medium text-slate-800">{{ $money($history->fine_amount) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4 border-t border-slate-200 pt-3 font-bold"><dt class="text-slate-700">Total tagihan</dt><dd class="shrink-0 text-right text-slate-900">{{ $money($grandTotal) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Dibayar</dt><dd class="shrink-0 text-right font-semibold text-emerald-700">{{ $money($totalPaid) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-4 border-t border-slate-200 pt-3 font-bold"><dt class="text-slate-700">Sisa</dt><dd class="shrink-0 text-right text-emerald-700">{{ $money(max(0, $grandTotal - $totalPaid)) }}</dd></div>
                        </dl>
                    </div>
                </section>

                @if(count($guarantees))
                    <section class="border-t border-dashed border-slate-200 py-6 sm:py-7">
                        <h2 class="text-base font-bold text-slate-900">Jaminan</h2>
                        <div class="mt-3 divide-y divide-slate-100 border-y border-slate-100">
                            @foreach($guarantees as $guarantee)
                                <div class="flex items-center justify-between gap-4 py-3.5 text-sm">
                                    <div>
                                        <p class="font-semibold text-slate-800">{{ $guarantee['type_label'] ?? $guarantee['type'] ?? 'Jaminan' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Nomor: {{ $guarantee['id_number'] ?? '-' }} · Deposit: {{ $money($guarantee['deposit_amount'] ?? 0) }}</p>
                                    </div>
                                    <span class="shrink-0 text-right text-xs font-semibold {{ ($guarantee['status'] ?? '') === 'returned' ? 'text-emerald-700' : 'text-slate-500' }}">
                                        {{ ($guarantee['status'] ?? '') === 'returned' ? 'Dikembalikan' : ucfirst($guarantee['status'] ?? 'Tercatat') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($history->notes)
                    <footer class="border-t border-dashed border-slate-200 py-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Catatan</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $history->notes }}</p>
                    </footer>
                @endif
            </div>
        </article>
    </div>

    @push('scripts')
    <script>
    function downloadInvoiceAndOpenWa(pdfUrl, customerName, invoiceNumber, customerPhone) {
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
            "Halo " + customerName + ",\n\n" +
            "Berikut invoice sewa Anda " + invoiceNumber + ". " +
            "Mohon cek file PDF yang baru saja di-download, lalu lampirkan di sini ya.\n\n" +
            "Terima kasih."
        );

        setTimeout(function() {
            window.open("https://wa.me/" + customerPhone + "?text=" + message, '_blank');
        }, 500);
    }
    </script>
    @endpush
@endsection
