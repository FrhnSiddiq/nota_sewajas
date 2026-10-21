{{-- resources/views/reports/pdf/stock.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Stok Jas</title>
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
            width: 50%;
            padding: 10px 14px;
            background: #f5f6fa;
            border-left: 3px solid #1a1a2e;
            vertical-align: top;
        }
        .meta-box + .meta-box { margin-left: 8px; }
        .meta-box .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #888; margin-bottom: 3px; }
        .meta-box .value { font-size: 12px; font-weight: 700; color: #1a1a2e; }

        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            padding: 0 2px;
        }
        .summary-card {
            display: table-cell;
            width: 20%;
            padding: 12px 10px;
            background: #1a1a2e;
            color: #fff;
            text-align: center;
            vertical-align: middle;
        }
        .summary-card + .summary-card { border-left: 2px solid rgba(255,255,255,0.15); }
        .summary-card .s-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.8px; opacity: 0.75; margin-bottom: 4px; }
        .summary-card .s-value { font-size: 14px; font-weight: 700; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        thead tr {
            background: #1a1a2e;
            color: #fff;
        }
        thead th {
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            letter-spacing: 0.4px;
            font-size: 9.5px;
            text-transform: uppercase;
        }
        tbody tr:nth-child(even) { background: #f9f9fc; }
        tbody tr:nth-child(odd)  { background: #ffffff; }
        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #ebebf3;
            vertical-align: middle;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .footer {
            margin-top: 18px;
            display: table;
            width: 100%;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #e0e0e0;
            padding-top: 8px;
        }
        .footer-left  { display: table-cell; vertical-align: bottom; }
        .footer-right { display: table-cell; text-align: right; vertical-align: bottom; }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #aaa;
            font-style: italic;
        }

        .condition-habis { color: #dc3545; font-weight: 700; }
        .condition-hampir { color: #f0ad4e; font-weight: 700; }
        .condition-tersedia { color: #28a745; font-weight: 700; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="header-left">
            <div class="badge">LAPORAN STOK JAS</div>
            <h1>Rental Management System</h1>
            <p>{{ $branchName }}</p>
        </div>
        <div class="header-right">
            <p style="font-size:11px; font-weight:600;">Dicetak oleh: {{ $generatedBy }}</p>
            <p style="margin-top:4px;">{{ $generatedAt }}</p>
        </div>
    </div>

    {{-- META --}}
    <div class="meta-row">
        <div class="meta-box">
            <div class="label">Cabang</div>
            <div class="value">{{ $branchName }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Total Produk</div>
            <div class="value">{{ $products->count() }}</div>
        </div>
    </div>

    {{-- TABLE --}}
    @if($products->isEmpty())
        <p class="no-data">Tidak ada data stok untuk periode ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th>Nama Produk</th>
                    <th>Kode</th>
                    <th>Kategori</th>
                    <th class="text-center">Stok Total</th>
                    <th class="text-center">Tersedia</th>
                    <th class="text-center">Kondisi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $i => $product)
                    @php
                        $conditionClass = match(true) {
                            $product->stock_available == 0 => 'condition-habis',
                            $product->stock_available <= 2 => 'condition-hampir',
                            default => 'condition-tersedia',
                        };
                        $conditionText = match(true) {
                            $product->stock_available == 0 => 'Habis',
                            $product->stock_available <= 2 => 'Hampir Habis',
                            default => 'Tersedia',
                        };
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td style="font-weight:600;">{{ $product->name }}</td>
                        <td>{{ $product->code ?? '—' }}</td>
                        <td>{{ $product->category?->name ?? '—' }}</td>
                        <td class="text-center">{{ $product->stock_total }}</td>
                        <td class="text-center" style="font-weight:700;">{{ $product->stock_available }}</td>
                        <td class="text-center {{ $conditionClass }}">{{ $conditionText }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        <div class="footer-left">
            Rental Management System &mdash; Dokumen ini dibuat secara otomatis
        </div>
        <div class="footer-right">
            Dicetak: {{ $generatedAt }} &nbsp;|&nbsp; {{ $generatedBy }}
        </div>
    </div>

</body>
</html>
