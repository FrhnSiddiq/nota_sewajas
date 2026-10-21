@php
    $outputMode = 'pdf';
    $printedBy = auth()->user()?->name ?? ($rental->createdBy?->name ?? \App\Services\SettingsService::get('app_name', 'SewaJas'));
    $printedAt = now()->format('d M Y H:i');
@endphp

@include('rentals.partials.premium-doc-head', [
    'docTitle' => 'Detail Penyewaan - ' . ($rental->invoice_number ?? ''),
    'outputMode' => $outputMode,
])

<div class="receipt-compact-wrapper">
    <div class="receipt-compact-card">

        <div class="receipt-header">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="vertical-align:middle; padding:0;">
                        <div class="receipt-header-label">DETAIL PENYEWAAN</div>
                        <div style="font-size:9px; color:#64748B; margin-top:2px;">Dicetak {{ $printedAt }} oleh {{ $printedBy }}</div>
                    </td>
                    <td style="vertical-align:middle; text-align:right;">
                        <span class="status-badge status-{{ $rental->rental_status === 'returned' ? 'paid' : ($rental->rental_status === 'cancelled' ? 'unpaid' : 'unpaid') }}">
                            {{ strtoupper($rental->rental_status ?? 'WAITING') }}
                        </span>
                    </td>
                </tr>
            </table>
            <div class="receipt-header-meta">
                <span>{{ $rental->invoice_number ?? '-' }}</span>
                <span class="receipt-meta-sep">|</span>
                <span>Pembayaran: {{ strtoupper($rental->payment_status ?? '-') }}</span>
            </div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Informasi Pelanggan</div>
            <div class="receipt-row">
                <span class="receipt-label">Nama</span>
                <span class="receipt-value">{{ optional($rental->customer)->name ?? '-' }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">No. HP</span>
                <span class="receipt-value">{{ optional($rental->customer)->phone ?? '-' }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Cabang</span>
                <span class="receipt-value">{{ optional($rental->branch)->name ?? '-' }}</span>
            </div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Detail Penyewaan</div>
            <div class="receipt-row">
                <span class="receipt-label">Sewa</span>
                <span class="receipt-value">{{ optional($rental->rental_date)->format('d/m/Y') ?? '-' }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Kembali</span>
                <span class="receipt-value">{{ optional($rental->return_due_date)->format('d/m/Y') ?? '-' }}</span>
            </div>
            @if($rental->actual_return_date)
            <div class="receipt-row">
                <span class="receipt-label">Dikembalikan</span>
                <span class="receipt-value">{{ \Carbon\Carbon::parse($rental->actual_return_date)->format('d/m/Y H:i') }}</span>
            </div>
            @endif
            <div class="receipt-row">
                <span class="receipt-label">Durasi</span>
                <span class="receipt-value">{{ $rental->duration_days ?? 0 }} hari</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Metode</span>
                <span class="receipt-value">{{ strtoupper($rental->payment_method ?? '-') }}</span>
            </div>
            @if($rental->notes)
            <div class="receipt-row">
                <span class="receipt-label">Catatan</span>
                <span class="receipt-value">{{ $rental->notes }}</span>
            </div>
            @endif
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Produk yang Disewa</div>
            <div class="receipt-items">
                @foreach($rental->items as $item)
                    <div class="receipt-item">
                        <span class="receipt-item-name">{{ $item->product_name ?? optional($item->product)->name ?? 'Produk #' . $item->product_id }}</span>
                        <span class="receipt-item-qty">x{{ $item->quantity }} ({{ $item->product_size ?? '-' }})</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Ringkasan Pembayaran</div>
            <div class="receipt-total-section">
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Subtotal</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->subtotal ?? 0), 0, ',', '.') }}</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Diskon</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->discount ?? 0), 0, ',', '.') }}</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Total</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->total_amount ?? 0), 0, ',', '.') }}</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Dibayar</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->paid_amount ?? 0), 0, ',', '.') }}</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Sisa</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->remaining_amount ?? 0), 0, ',', '.') }}</span>
                </div>
                @if((float)($rental->late_fee ?? 0) > 0)
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Denda</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->late_fee ?? 0), 0, ',', '.') }}</span>
                </div>
                @endif
                @if((float)($rental->change_amount ?? 0) > 0)
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Kembalian</span>
                    <span class="receipt-total-value">Rp {{ number_format((float)($rental->change_amount ?? 0), 0, ',', '.') }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Petugas</div>
            <div class="receipt-row">
                <span class="receipt-label">Dibuat oleh</span>
                <span class="receipt-value">{{ $printedBy }}</span>
            </div>
            @if($rental->returnedBy)
            <div class="receipt-row">
                <span class="receipt-label">Dikembalikan oleh</span>
                <span class="receipt-value">{{ $rental->returnedBy->name }}</span>
            </div>
            @endif
        </div>

    </div>
</div>

@include('rentals.partials.doc-close-html')
