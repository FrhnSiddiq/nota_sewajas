@php
    $rental = $rental ?? null;
    $rentalStatus = $rental->rental_status ?? 'waiting';
    $rentalStatusBadge = match($rentalStatus) {
        'returned' => strtoupper($rental->status_label),
        'active', 'waiting' => 'SEDANG DISEWA',
        'overdue' => 'TERLAMBAT',
        'cancelled' => 'DIBATALKAN',
        default => strtoupper($rentalStatus),
    };
    $rentalStatusBadgeClass = match($rentalStatus) {
        'returned' => $rental->isCompleted() ? 'status-paid' : 'status-unpaid',
        'active', 'waiting' => 'status-paid',
        'overdue' => 'status-overdue',
        'cancelled' => 'status-unpaid',
        default => 'status-unpaid',
    };
@endphp

<div class="info-card">
    <div class="info-card-title">Detail Rental</div>
    <div class="info-row">
        <span class="info-label">Rental ID</span>
        <span class="info-value">#{{ $rental->id ?? '-' }}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Tanggal Sewa</span>
        <span class="info-value">{{ optional($rental->rental_date)->format('d M Y') ?? '-' }}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Tanggal Kembali</span>
        <span class="info-value">{{ optional($rental->return_due_date)->format('d M Y') ?? '-' }}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Durasi</span>
        <span class="info-value">{{ $rental->duration_days ?? 0 }} hari</span>
    </div>
    <div class="info-row">
        <span class="info-label">Metode Bayar</span>
        <span class="info-value">
            @php
                $method = $rental->payment_method ?? null;
                if ($method === 'cash') $methodLabel = 'Tunai';
                elseif ($method === 'transfer') $methodLabel = 'Transfer';
                elseif ($method === 'qris') $methodLabel = 'QRIS';
                else $methodLabel = $method ?? '-';
            @endphp
            {{ $methodLabel }}
        </span>
    </div>
    <table style="width:100%; border-collapse:collapse; margin-top:6px;">
        <tr>
            <td style="padding-top:6px; border-top:1px solid #E5E7EB; font-size:10px; color:#64748B; width:100px; vertical-align:middle;">
                Status
            </td>
            <td style="padding-top:6px; border-top:1px solid #E5E7EB; vertical-align:middle;">
                <span class="status-badge {{ $rentalStatusBadgeClass }}">{{ $rentalStatusBadge }}</span>
            </td>
        </tr>
    </table>
</div>