@php
    $appName = \App\Services\SettingsService::get('app_name', 'MaisonSewa');
    $items = $history->items_snapshot ?? [];
    $payments = $history->payments_snapshot ?? [];
    $guarantees = $history->guarantees_snapshot ?? [];
    $grandTotal = (float) $history->total_amount + (float) $history->fine_amount;
    $totalPaid = (float) $history->paid_amount + (float) $history->fine_paid_amount;
    $money = fn ($amount) => 'Rp ' . number_format((float) ($amount ?? 0), 0, ',', '.');

    // Status dokumen: dinamis, jangan hardcode (dulu pill "LUNAS" selalu tampil
    // meski sisa tagihan masih ada).
    $isPaid = ($grandTotal - $totalPaid) <= 0.00001;
    $stateLabel = match (true) {
        ($history->rental_status ?? null) === 'waiting'   => 'BOOKING',
        ($history->rental_status ?? null) === 'cancelled' => 'DIBATALKAN',
        ($history->rental_status ?? null) === 'overdue'   => 'TERLAMBAT',
        ($history->rental_status ?? null) === 'active'    => 'SEDANG DISEWA',
        default                                           => 'SELESAI',
    };
    $statusPill = $stateLabel . ' · ' . ($isPaid ? 'LUNAS' : 'BELUM LUNAS');
    $statusPillClass = ($stateLabel === 'SELESAI' && $isPaid) ? 'pill' : 'pill pill-warn';

    // QR verifikasi → halaman scan (menerima INV…, hidup maupun arsip rental).
    $qrBase64 = null;
    try {
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(150)
            ->generate(route('rentals.scan.show', ['invoice' => $history->invoice_number]));
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode((string) $qrSvg);
    } catch (\Throwable $e) {
        $qrBase64 = null;
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $history->invoice_number }}</title>
    <style>
        @page { margin: 30px 36px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 16px; }
        .brand { color: #1d4ed8; font-size: 20px; font-weight: 700; }
        .document-label { color: #64748b; font-size: 9px; font-weight: 700; letter-spacing: 2px; text-align: right; }
        .invoice-number { color: #0f172a; font-size: 17px; font-weight: 700; margin-top: 5px; }
        .muted { color: #64748b; }
        .section { margin-top: 20px; }
        .section-title { border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 9px; font-weight: 700; letter-spacing: 1px; margin-bottom: 8px; padding-bottom: 6px; text-transform: uppercase; }
        .info { width: 100%; }
        .info td { padding: 3px 0; vertical-align: top; width: 50%; }
        .label { color: #64748b; display: inline-block; min-width: 95px; }
        table.items, table.payments { border-collapse: collapse; width: 100%; }
        table.items th, table.payments th { background: #f1f5f9; color: #475569; font-size: 8px; padding: 8px 6px; text-align: left; }
        table.items td, table.payments td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; vertical-align: top; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        .summary { margin-left: auto; margin-top: 12px; width: 48%; }
        .summary td { padding: 4px 0; }
        .summary .total td { border-top: 1px solid #cbd5e1; color: #1d4ed8; font-size: 12px; font-weight: 700; padding-top: 8px; }
        .summary .paid td { color: #047857; font-weight: 700; }
        .pill { background: #dcfce7; border-radius: 10px; color: #166534; display: inline-block; font-size: 8px; font-weight: 700; padding: 4px 8px; }
        .pill-warn { background: #fef3c7; color: #92400e; }
        .notes { background: #f8fafc; padding: 10px; }
        .footer { border-top: 1px solid #e2e8f0; color: #64748b; font-size: 8px; margin-top: 22px; padding-top: 10px; text-align: center; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">{{ $appName }}</div>
                <div class="muted">{{ $history->branch->name ?? 'Cabang' }}</div>
            </td>
            <td class="document-label">INVOICE PENYEWAAN<br><span class="{{ $statusPillClass }}">{{ $statusPill }}</span></td>
        </tr>
    </table>

    <div class="invoice-number">{{ $history->invoice_number }}</div>
    <div class="muted">Diterbitkan {{ $history->completed_at?->format('d M Y, H:i') ?? '-' }}</div>

    <div class="section">
        <table class="info">
            <tr>
                <td>
                    <div class="section-title">Penyewa</div>
                    <div><strong>{{ $history->customer->name ?? 'Pelanggan tidak tersedia' }}</strong></div>
                    <div class="muted">{{ $history->customer->phone ?? '-' }}</div>
                </td>
                <td>
                    <div class="section-title">Periode penyewaan</div>
                    <div><span class="label">Mulai</span>{{ $history->rental_date?->format('d M Y') ?? '-' }}</div>
                    <div><span class="label">Jatuh tempo</span>{{ $history->return_due_date?->format('d M Y') ?? '-' }}</div>
                    <div><span class="label">Dikembalikan</span>{{ $history->actual_return_date?->format('d M Y') ?? '-' }}</div>
                    <div><span class="label">Durasi</span>{{ $history->duration_days }} hari</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="info">
            <tr>
                <td>
                    <div class="section-title">Petugas transaksi</div>
                    <div><span class="label">Dibuat oleh</span>{{ $history->createdBy->name ?? '-' }}</div>
                    <div><span class="label">Dikembalikan oleh</span>{{ $history->returnedBy->name ?? '-' }}</div>
                </td>
                <td></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Rincian penyewaan</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Produk</th><th>Ukuran</th><th class="center">Qty</th>
                    <th class="right">Harga / hari</th><th class="center">Hari</th><th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item['product_name'] ?? 'Produk' }}</strong>
                            @if(!empty($item['product_snapshot']['code']))
                                <br><span class="muted">{{ $item['product_snapshot']['code'] }}</span>
                            @endif
                            @if(!empty($item['return_condition']))
                                <br><span class="muted">Kondisi kembali: {{ ucfirst(str_replace('_', ' ', $item['return_condition'])) }}</span>
                            @endif
                        </td>
                        <td>{{ $item['product_size'] ?? '-' }}</td>
                        <td class="center">{{ $item['quantity'] ?? 0 }}</td>
                        <td class="right">{{ $money($item['price_per_day'] ?? 0) }}</td>
                        <td class="center">{{ $item['duration_days'] ?? $history->duration_days }}</td>
                        <td class="right">{{ $money($item['subtotal'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="center muted">Tidak ada snapshot item.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section avoid-break">
        <div class="section-title">Ringkasan pembayaran</div>
        <table class="summary">
            <tr><td>Subtotal</td><td class="right">{{ $money($history->subtotal) }}</td></tr>
            <tr><td>Diskon</td><td class="right">−{{ $money($history->discount) }}</td></tr>
            <tr><td>Total sewa</td><td class="right">{{ $money($history->total_amount) }}</td></tr>
            <tr><td>Denda telat</td><td class="right">{{ $money($history->late_fee) }}</td></tr>
            <tr><td>Total denda</td><td class="right">{{ $money($history->fine_amount) }}</td></tr>
            <tr class="total"><td>Total tagihan</td><td class="right">{{ $money($grandTotal) }}</td></tr>
            <tr class="paid"><td>Total dibayar</td><td class="right">{{ $money($totalPaid) }}</td></tr>
            <tr><td>Sisa tagihan</td><td class="right">{{ $money(max(0, $grandTotal - $totalPaid)) }}</td></tr>
        </table>
    </div>

    @if(count($payments))
        <div class="section">
            <div class="section-title">Rincian pembayaran</div>
            <table class="payments">
                <thead><tr><th>No. pembayaran</th><th>Tanggal</th><th>Jenis</th><th>Metode</th><th class="right">Nominal</th></tr></thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td>{{ $payment['payment_number'] ?? '-' }}</td>
                            <td>{{ !empty($payment['paid_at']) ? \Carbon\Carbon::parse($payment['paid_at'])->format('d/m/Y H:i') : '-' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment['type'] ?? 'rental')) }}</td>
                            <td>{{ $payment['method_label'] ?? ucfirst($payment['method'] ?? '-') }}</td>
                            <td class="right">{{ (float) ($payment['amount'] ?? 0) < 0 ? '−' : '' }}{{ $money(abs((float) ($payment['amount'] ?? 0))) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(count($guarantees))
        <div class="section">
            <div class="section-title">Jaminan</div>
            <table class="payments">
                <thead><tr><th>Jenis</th><th>Nomor identitas</th><th>Status</th><th class="right">Deposit</th></tr></thead>
                <tbody>
                    @foreach($guarantees as $guarantee)
                        <tr>
                            <td>{{ $guarantee['type_label'] ?? $guarantee['type'] ?? 'Jaminan' }}</td>
                            <td>{{ $guarantee['id_number'] ?? '-' }}</td>
                            <td>{{ ucfirst($guarantee['status'] ?? '-') }}</td>
                            <td class="right">{{ $money($guarantee['deposit_amount'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($history->notes)
        <div class="section avoid-break">
            <div class="section-title">Catatan</div>
            <div class="notes">{{ $history->notes }}</div>
        </div>
    @endif

    @if($qrBase64)
        <div class="section avoid-break qr-block">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 116px; vertical-align: middle;">
                        <img src="{{ $qrBase64 }}" alt="QR Verifikasi" style="width: 100px; height: 100px;">
                    </td>
                    <td style="vertical-align: middle;">
                        <div class="section-title">Verifikasi dokumen</div>
                        <div>Pindai QR ini untuk membuka detail penyewaan di halaman scan {{ $appName }}.</div>
                        <div class="muted">{{ $history->invoice_number }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="footer">Invoice ini dibuat otomatis oleh {{ $appName }} dan merupakan bukti transaksi penyewaan.</div>
</body>
</html>
