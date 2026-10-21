{{-- resources/views/reports/pdf/customers.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Customer</title>
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
            <h1>Laporan Customer</h1>
            <p>Data customer dan riwayat transaksi</p>
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
            <div class="label">Pencarian</div>
            <div class="value">{{ $search ?: 'Semua' }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Total Customer</div>
            <div class="value">{{ $customers->count() }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Nama</th>
                <th style="width: 14%;">No. HP</th>
                <th style="width: 18%;">Email</th>
                <th style="width: 12%;">Total Transaksi</th>
                <th style="width: 16%;">Total Nilai</th>
                <th style="width: 12%;">Terakhir Transaksi</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $c)
                <tr>
                    <td>{{ $c->name }}</td>
                    <td>{{ $c->phone ?? '-' }}</td>
                    <td>{{ $c->email ?? '-' }}</td>
                    <td style="text-align:center;">{{ $c->total_rentals }}</td>
                    <td style="text-align:right;">{{ $c->total_transaction_value ? 'Rp ' . number_format((float) $c->total_transaction_value, 0, ',', '.') : 'Rp 0' }}</td>
                    <td>{{ $c->last_rental_date ? \Carbon\Carbon::parse($c->last_rental_date)->format('d/m/Y') : '-' }}</td>
                    <td style="text-align:center;">{{ $c->is_blacklisted ? 'Blacklist' : 'Aktif' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding: 20px; color:#888;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Total {{ $customers->count() }} record &bull; Dicetak pada {{ $generatedAt }} oleh {{ $generatedBy }}
    </div>
</body>
</html>
