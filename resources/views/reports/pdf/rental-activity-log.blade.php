{{-- resources/views/reports/pdf/rental-activity-log.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Aktivitas Penyewaan</title>
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
            <h1>Log Aktivitas Penyewaan</h1>
            <p>Data agregat semua aktivitas transaksi penyewaan</p>
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
            <div class="label">Dicetak</div>
            <div class="value">{{ $generatedAt }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Waktu</th>
                <th style="width: 14%;">Cabang</th>
                <th style="width: 14%;">User</th>
                <th style="width: 12%;">Aksi</th>
                <th style="width: 26%;">Deskripsi</th>
                <th style="width: 10%;">Invoice</th>
                <th style="width: 6%;">IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                @php
                    $actionLabels = [
                        'create_rental' => 'Buat Sewa',
                        'rental_deleted' => 'Batalkan Sewa',
                        'handover_rental' => 'Selesaikan Sewa',
                        'return_rental' => 'Selesaikan Sewa',
                        'recalculate_payment_status' => 'Ubah Sewa',
                        'update_transaction' => 'Ubah Sewa',
                        'delete_transaction' => 'Batalkan Sewa',
                        'process_payment' => 'Terima Pembayaran',
                        'record_refund' => 'Batalkan Pembayaran',
                        'create_payment' => 'Terima Pembayaran',
                        'update_payment' => 'Ubah Pembayaran',
                        'delete_payment' => 'Batalkan Pembayaran',
                        'void_payment' => 'Batalkan Pembayaran',
                        'refund_payment' => 'Batalkan Pembayaran',
                        'create_customer' => 'Tambah Pelanggan',
                        'update_customer' => 'Ubah Data Pelanggan',
                        'delete_customer' => 'Hapus Pelanggan',
                        'soft_delete_customer' => 'Hapus Pelanggan',
                        'force_delete_customer' => 'Hapus Pelanggan',
                        'restore_customer' => 'Pulihkan Pelanggan',
                        'create_product' => 'Tambah Produk',
                        'update_product' => 'Ubah Produk',
                        'update_product_stock' => 'Ubah Stok',
                        'delete_product' => 'Hapus Produk',
                        'create_user' => 'Tambah Pengguna',
                        'update_user' => 'Ubah Pengguna',
                        'delete_user' => 'Hapus Pengguna',
                        'activate_user' => 'Aktifkan Pengguna',
                        'deactivate_user' => 'Nonaktifkan Pengguna',
                    ];
                    $rental = \App\Models\Rental::find($log->model_id);
                @endphp
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ ($log->model_type === \App\Models\Rental::class ? $log->rental?->branch?->name : null) ?? $log->branch?->name ?? '-' }}</td>
                    <td>{{ $log->user?->name ?? '-' }}</td>
                    <td>{{ $actionLabels[$log->action] ?? $log->action }}</td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $rental?->invoice_number ?? ($log->model_id ?? '-') }}</td>
                    <td>{{ $log->ip_address ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding: 20px; color:#888;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Total {{ $logs->count() }} record &bull; Dicetak pada {{ $generatedAt }} oleh {{ $generatedBy }}
    </div>
</body>
</html>
