@php
    $rental = $rental ?? null;
    $receipt = $receipt ?? [];

    $receiptNumber = $receipt['receipt_number'] ?? ('RCPT-' . ($rental->invoice_number ?? '-'));
    $receiptDate = now('Asia/Jakarta')->format('d M Y H:i');

    $customerName = optional($rental->customer)->name ?? '-';
    $invoiceNumber = $rental->invoice_number ?? '-';
    $rentalDate = optional($rental->rental_date)->format('d M Y') ?? '-';
    $returnDueDate = optional($rental->return_due_date)->format('d M Y') ?? '-';
    $actualReturnDate = optional($rental->returned_at)?->format('d M Y H:i');

    $rentalStatus = $rental->rental_status ?? 'waiting';
    $statusBadge = match($rentalStatus) {
        'returned' => strtoupper($rental->status_label),
        'active' => 'SEDANG DISEWA',
        'overdue' => 'TERLAMBAT',
        'cancelled' => 'DIBATALKAN',
        'waiting' => 'BOOKING',
        default => strtoupper($rentalStatus),
    };
    $statusBadgeClass = match($rentalStatus) {
        'returned' => $rental->isCompleted() ? 'status-paid' : 'status-unpaid',
        'active' => 'status-paid',
        'overdue' => 'status-overdue',
        'cancelled' => 'status-unpaid',
        default => 'status-unpaid',
    };

    $totalAmount = (float) ($rental->total_amount ?? 0);

    $createdByName = optional($rental->createdBy)->name ?? \App\Services\SettingsService::get('app_name', 'SewaJas');
    $processedByName = optional($rental->returnedBy)->name ?? null;

    $appName = \App\Services\SettingsService::get('app_name', 'SewaJas');
    $appLogo = \App\Services\SettingsService::get('app_logo');
    $appLogoUrl = '';
    if ($appLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($appLogo)) {
        $fullPath = storage_path('app/public/' . $appLogo);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $appLogoUrl = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fullPath));
    }

    $companyAddress = \App\Services\SettingsService::get('company_address');
    $companyPhone = \App\Services\SettingsService::get('company_phone');
    $companyWebsite = \App\Services\SettingsService::get('company_website');

    $qrBase64 = null;
    try {
        // Arahkan ke halaman scan (dikunci nomor invoice, tidak pernah dipakai ulang)
        // agar konsisten dengan QR pada invoice A4 dan tahan terhadap reuse ID numerik.
        $qrRoute = route('rentals.scan.show', ['invoice' => $rental->invoice_number]);
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(160)->generate($qrRoute);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode((string) $qrSvg);
    } catch (\Throwable $e) {
        $qrBase64 = null;
    }
@endphp

<div class="receipt-compact-wrapper">
    <div class="receipt-compact-card">

        {{-- HEADER --}}
        <div class="receipt-header">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="width:54px; vertical-align:middle; padding:0;">
                        @if($appLogoUrl)
                            <img src="{{ $appLogoUrl }}" alt="{{ $appName }} Logo" style="width:42px; height:42px; border-radius:10px; object-fit:contain; background:rgba(255,255,255,0.15); padding:3px;">
                        @else
                            <div style="width:42px; height:42px; border-radius:10px; background:rgba(255,255,255,0.15); color:#ffffff; font-weight:800; font-size:16px; text-align:center; line-height:42px; border:1px solid rgba(255,255,255,0.3);">
                                {{ mb_substr($appName, 0, 2) }}
                            </div>
                        @endif
                    </td>
                    <td style="vertical-align:middle; padding-left:10px;">
                        <div class="receipt-app-name">{{ $appName }}</div>
                    </td>
                    <td style="vertical-align:middle; text-align:right;">
                        <div class="receipt-header-label">RECEIPT</div>
                    </td>
                </tr>
            </table>
            <div class="receipt-header-meta">
                <span>{{ $receiptNumber }}</span>
                <span class="receipt-meta-sep">|</span>
                <span>{{ $receiptDate }}</span>
                <span class="receipt-brand">MaisonSewa</span>
            </div>
        </div>

        {{-- CUSTOMER + BOOKING --}}
        <div class="receipt-section">
            <div class="receipt-row">
                <span class="receipt-label">Customer</span>
                <span class="receipt-value">{{ $customerName }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Invoice</span>
                <span class="receipt-value">{{ $invoiceNumber }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Sewa</span>
                <span class="receipt-value">{{ $rentalDate }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Kembali</span>
                <span class="receipt-value">{{ $returnDueDate }}</span>
            </div>
            @if($actualReturnDate)
            <div class="receipt-row">
                <span class="receipt-label">Dikembalikan</span>
                <span class="receipt-value">{{ $actualReturnDate }}</span>
            </div>
            @endif
            <div class="receipt-row receipt-row-status">
                <span class="receipt-label">Status</span>
                <span class="receipt-value"><span class="status-badge {{ $statusBadgeClass }}">{{ $statusBadge }}</span></span>
            </div>
        </div>

        {{-- ITEMS --}}
        <div class="receipt-section">
            <div class="receipt-section-title">Produk yang Disewa</div>
            <div class="receipt-items">
                @foreach($rental->items as $item)
                    <div class="receipt-item">
                        <span class="receipt-item-name">{{ $item->product_name ?? optional($item->product)->name ?? 'Produk #' . $item->product_id }}</span>
                        <span class="receipt-item-qty">x{{ $item->quantity }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- QR CODE --}}
        <div class="receipt-section receipt-qr-section">
            <div class="receipt-qr-wrapper">
                @if(!empty($qrBase64))
                    <img src="{{ $qrBase64 }}" alt="QR Code" class="receipt-qr-image">
                @endif
            </div>
            <div class="receipt-qr-label">Scan untuk detail rental</div>
        </div>

        {{-- PETUGAS --}}
        <div class="receipt-section receipt-petugas-section">
            <div class="receipt-row">
                <span class="receipt-label">Dibuat oleh</span>
                <span class="receipt-value">{{ $createdByName }}</span>
            </div>
            @if($processedByName)
            <div class="receipt-row">
                <span class="receipt-label">Diproses oleh</span>
                <span class="receipt-value">{{ $processedByName }}</span>
            </div>
            @endif
        </div>

        {{-- TOTAL --}}
        <div class="receipt-total-section">
            <div class="receipt-total-row">
                <span class="receipt-total-label">Total</span>
                <span class="receipt-total-value">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="receipt-footer">
            @if($companyAddress || $companyPhone || $companyWebsite)
            <div class="receipt-company-info">
                @if($companyAddress)
                <div class="receipt-company-line">{{ $companyAddress }}</div>
                @endif
                <div class="receipt-company-line">
                    @if($companyPhone)
                    {{ $companyPhone }}
                    @endif
                </div>
                @if($companyWebsite)
                <div class="receipt-company-line">{{ $companyWebsite }}</div>
                @endif
            </div>
            @endif
        </div>

    </div>
</div>
