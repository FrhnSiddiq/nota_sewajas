{{-- resources/views/reports/pdf/revenue.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Pendapatan</title>
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

        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            padding: 0 2px;
        }
        .summary-card {
            display: table-cell;
            width: 33.33%;
            padding: 12px 16px;
            background: #1a1a2e;
            color: #fff;
            text-align: center;
            vertical-align: middle;
        }
        .summary-card.accent { background: #e8b04b; color: #1a1a2e; }
        .summary-card.light { background: #f0f0f5; color: #1a1a2e; }
        .summary-card + .summary-card { border-left: 2px solid rgba(255,255,255,0.15); }
        .summary-card .s-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; opacity: 0.75; margin-bottom: 4px; }
        .summary-card .s-value { font-size: 15px; font-weight: 700; }

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

        .total-row td {
            background: #1a1a2e !important;
            color: #fff;
            font-weight: 700;
            font-size: 11px;
            border-bottom: none;
            padding: 9px 10px;
        }
        .total-row .accent { color: #e8b04b; }

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
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="header-left">
            <div class="badge">LAPORAN PENDAPATAN</div>
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
            <div class="label">Periode Mulai</div>
            <div class="value">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : '—' }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Periode Akhir</div>
            <div class="value">{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : '—' }}</div>
        </div>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="summary-row">
        <div class="summary-card">
            <div class="s-label">Total Hari Transaksi</div>
            <div class="s-value">{{ $revenueData->count() }}</div>
        </div>
        <div class="summary-card accent">
            <div class="s-label">Total Pendapatan</div>
            <div class="s-value">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
        </div>
        <div class="summary-card light">
            <div class="s-label">Total Denda</div>
            <div class="s-value">Rp {{ number_format($totalLateFee, 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- TABLE --}}
    @if($revenueData->isEmpty())
        <p class="no-data">Tidak ada data pendapatan untuk periode ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th class="text-center">Total Rental</th>
                    <th class="text-right">Denda</th>
                    <th class="text-right">Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($revenueData as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row->date)->isoFormat('dddd, D MMMM Y') }}</td>
                        <td class="text-center">{{ number_format($row->total_rentals) }}</td>
                        <td class="text-right">
                            @if ($row->total_late_fee > 0)
                                <span style="color:#c0392b; font-weight:600;">
                                    Rp {{ number_format($row->total_late_fee, 0, ',', '.') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right" style="font-weight:700; color:#15803D;">
                            Rp {{ number_format($row->total_revenue, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach

                {{-- TOTAL ROW --}}
                <tr class="total-row">
                    <td style="text-align:right; letter-spacing:0.5px;">TOTAL KESELURUHAN</td>
                    <td class="text-center">{{ number_format($revenueData->sum('total_rentals')) }}</td>
                    <td class="text-right accent">Rp {{ number_format($totalLateFee, 0, ',', '.') }}</td>
                    <td class="text-right accent">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
                </tr>
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
