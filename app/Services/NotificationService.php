<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\Relation;

class NotificationService
{
    private function morphKey(string $class): string
    {
        $map = Relation::morphMap();
        $flipped = array_flip($map);

        return $flipped[$class] ?? $class;
    }

    public function broadcast(
        array $recipients,
        string $type,
        string $title,
        string $message,
        array $meta = [],
        ?int $branchId = null,
        ?string $actionUrl = null,
        string $icon = 'bell',
        string $color = 'blue'
    ): void {
        $recipients = array_values(array_filter($recipients));
        if ($recipients === []) {
            return;
        }

        $now  = now();
        $rows = array_map(function ($recipient) use ($type, $title, $message, $meta, $branchId, $actionUrl, $icon, $color, $now) {
            return [
                'notifiable_type' => $this->morphKey($recipient['type']),
                'notifiable_id'   => $recipient['id'],
                'branch_id'       => $branchId,
                'type'            => $type,
                'title'           => $title,
                'message'         => $recipient['message'] ?? $message,
                'icon'            => $icon,
                'color'           => $color,
                'meta'            => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'action_url'      => $actionUrl,
                'is_read'         => false,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }, $recipients);

        Notification::insert($rows);
    }

    public function rentalCreated($rental, array $userIds): void
    {
        $recipients = array_map(fn($uid) => ['type' => \App\Models\User::class, 'id' => $uid], array_values(array_unique(array_filter($userIds))));
        $this->broadcast($recipients, 'rental_new', 'Penyewaan Baru',
            "{$rental->customer->name} — {$rental->invoice_number}",
            ['invoice_no' => $rental->invoice_number], $rental->branch_id);
    }

    public function rentalReturned($rental, array $userIds): void
    {
        $recipients = array_map(fn($uid) => ['type' => \App\Models\User::class, 'id' => $uid], array_values(array_unique(array_filter($userIds))));
        $this->broadcast($recipients, 'rental_return', 'Jas Dikembalikan',
            "{$rental->customer->name} mengembalikan jas — {$rental->invoice_number}",
            ['invoice_no' => $rental->invoice_number], $rental->branch_id);
    }

    public function rentalLate($rental, int $days, int $fine, array $userIds): void
    {
        $recipients = array_map(fn($uid) => ['type' => \App\Models\User::class, 'id' => $uid], array_values(array_unique(array_filter($userIds))));
        $this->broadcast($recipients, 'rental_late', 'Jas Telat',
            "{$rental->customer->name} telat {$days} hari. Denda Rp " . number_format($fine, 0, ',', '.'),
            ['invoice_no' => $rental->invoice_number, 'fine' => $fine], $rental->branch_id);
    }

    public function paymentReceived($rental, int $amount, array $userIds): void
    {
        $recipients = array_map(fn($uid) => ['type' => \App\Models\User::class, 'id' => $uid], array_values(array_unique(array_filter($userIds))));
        $this->broadcast($recipients, 'payment', 'Pembayaran Diterima',
            "{$rental->customer->name} — Rp " . number_format($amount, 0, ',', '.'),
            ['invoice_no' => $rental->invoice_number], $rental->branch_id);
    }

    public function returnReminder($rental, array $userIds): void
    {
        $recipients = array_map(fn($uid) => ['type' => \App\Models\User::class, 'id' => $uid], array_values(array_unique(array_filter($userIds))));
        $this->broadcast($recipients, 'reminder', 'Reminder Pengembalian',
            "{$rental->customer->name} — jatuh tempo hari ini",
            ['invoice_no' => $rental->invoice_number], $rental->branch_id);
    }
}
