{{-- resources/views/reports/pdf/payments.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Pembayaran</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            background: #fff;
        }

        .header {
            background: #1a1a2e;
            color: #fff;
            padding: 18px 24px;
            margin-bottom: 18px;
            display: table;
            width: 100%;
        }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; text-align: right; vertical-align: middle; }
        .header h1 { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
        .header p { font-size: 10px; opacity: 0.75; margin-top: 2px; }
        .header .badge {
            display: inline-block;
            background: #e8b04b;
            color: #1a1a2e;
            font-weight: 700;
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 4px;
        }

        .meta-row {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding: 0 2px;
        }
        .meta-box {
            display: table-cell;
            width: 33.33%;
            padding: 10px 14px;
            background: #f5f6fa;
            border-left: 3px solid #1a1a2e;
            vertical-align: top;
        }
        .meta-box + .meta-box { margin-left: 8px; }
        .meta-box .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #888; margin-bottom: 3px; }
        .meta-box .value { font-size: 12px; font-weight: 700; color: #1a1a2e; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        th {
            background: #1a1a2e;
            color: #fff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 8px 10px;
            text-align: left;
        }
        td {
            padding: 7px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        tr:nth-child(even) { background: #f9fafb; }

        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #888;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="badge">Laporan</div>
            <h1>Laporan Pembayaran</h1>
            <p>Riwayat pembayaran transaksi penyewaan</p>
        </div>
        <div class="header-right">
            <p>Dicetak: {{ $generatedAt }}</p>
            <p>Oleh: {{ $generatedBy }}</p>
        </div>
    </div>

    <div class="meta-row">
        <div class="meta-box">
            <div class="label">Cabang</div>
            <div class="value">{{ $branchName }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Periode</div>
            <div class="value">{{ $periodLabel ?? '-' }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Total Pembayaran</div>
            <div class="value">{{ $payments->count() }} transaksi</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Tanggal</th>
                <th style="width: 12%;">No. Pembayaran</th>
                <th style="width: 12%;">Invoice</th>
                <th style="width: 16%;">Pelanggan</th>
                <th style="width: 10%;">Jumlah</th>
                <th style="width: 10%;">Metode</th>
                <th style="width: 10%;">Tipe</th>
                <th style="width: 10%;">Diterima Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $p)
                <tr>
                    <td>{{ $p->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $p->payment_number }}</td>
                    <td>{{ $p->rental?->invoice_number ?? '-' }}</td>
                    <td>{{ $p->rental?->customer?->name ?? '-' }}</td>
                    <td style="text-align:right;">Rp {{ number_format((float) $p->amount, 0, ',', '.') }}</td>
                    <td style="text-align:center;">{{ $p->method_label ?? $p->method }}</td>
                    <td style="text-align:center;">{{ $p->type }}</td>
                    <td>{{ $p->receivedBy?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding: 20px; color:#888;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Total {{ $payments->count() }} record &bull; Dicetak pada {{ $generatedAt }} oleh {{ $generatedBy }}
    </div>
</body>
</html>
