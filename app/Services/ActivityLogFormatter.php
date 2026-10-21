<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogFormatter
{
    public static function format(?array $oldValues, ?array $newValues, ?string $modelType, ?int $modelId, ?string $action): string
    {
        $modelLabel = $modelType ? class_basename($modelType) : 'Data';

        if (empty($oldValues) && empty($newValues)) {
            return self::genericDescription($modelLabel, $modelId, $action);
        }

        if (empty($oldValues) && !empty($newValues)) {
            return self::creationDescription($modelLabel, $modelId, $newValues, $action);
        }

        if (!empty($oldValues) && empty($newValues)) {
            return self::deletionDescription($modelLabel, $modelId, $oldValues, $action);
        }

        return self::updateDescription($modelLabel, $modelId, $oldValues, $newValues, $action);
    }

    private static function genericDescription(string $modelLabel, ?int $modelId, string $action): string
    {
        $label = $modelId ? "{$modelLabel} #{$modelId}" : $modelLabel;

        return match (true) {
            str_starts_with($action, 'create_')     => "{$label} berhasil dibuat",
            str_starts_with($action, 'update_')     => "{$label} diperbarui",
            str_starts_with($action, 'delete_')     => "{$label} dihapus",
            str_starts_with($action, 'restore_')    => "{$label} dipulihkan",
            str_starts_with($action, 'activate_')   => "{$label} diaktifkan",
            str_starts_with($action, 'deactivate_') => "{$label} dinonaktifkan",
            str_starts_with($action, 'process_')    => "{$label} diproses",
            str_starts_with($action, 'return_')     => "{$label} dikembalikan",
            str_starts_with($action, 'record_')     => "{$label} dicatat",
            str_starts_with($action, 'handover_')   => "{$label} diserahkan",
            str_starts_with($action, 'recalculate_') => "{$label} dihitung ulang",
            default                                  => "{$label} - {$action}",
        };
    }

    private static function creationDescription(string $modelLabel, ?int $modelId, array $newValues, string $action): string
    {
        $label = $modelId ? "{$modelLabel} #{$modelId}" : $modelLabel;

        if (str_starts_with($action, 'create_payment') || str_starts_with($action, 'process_payment')) {
            $amount = Arr::get($newValues, 'amount') ?? Arr::get($newValues, 'total_amount') ?? Arr::get($newValues, 'paid_amount');
            if ($amount) {
                return "Pembayaran sebesar Rp " . number_format((float) $amount, 0, ',', '.') . " dicatat untuk {$label}";
            }
            return "Pembayaran dicatat untuk {$label}";
        }

        if (str_starts_with($action, 'create_rental')) {
            return "Penyewaan {$label} berhasil dibuat";
        }

        if ($modelLabel === 'Customer') {
            $name = Arr::get($newValues, 'name');
            return $name ? "Pelanggan '{$name}' ditambahkan" : "{$label} berhasil dibuat";
        }

        if ($modelLabel === 'Product') {
            $name = Arr::get($newValues, 'name');
            return $name ? "Produk '{$name}' ditambahkan" : "{$label} berhasil dibuat";
        }

        if ($modelLabel === 'User') {
            $name = Arr::get($newValues, 'name');
            return $name ? "User '{$name}' ditambahkan" : "{$label} berhasil dibuat";
        }

        return "{$label} berhasil dibuat";
    }

    private static function deletionDescription(string $modelLabel, ?int $modelId, array $oldValues, string $action): string
    {
        $label = $modelId ? "{$modelLabel} #{$modelId}" : $modelLabel;

        if ($modelLabel === 'Customer') {
            $name = Arr::get($oldValues, 'name') ?? Arr::get($oldValues, 'customer_name');
            return $name ? "Pelanggan '{$name}' dihapus" : "{$label} dihapus";
        }

        if ($modelLabel === 'Product') {
            $name = Arr::get($oldValues, 'name');
            return $name ? "Produk '{$name}' dihapus" : "{$label} dihapus";
        }

        if ($modelLabel === 'User') {
            $name = Arr::get($oldValues, 'name');
            return $name ? "User '{$name}' dihapus" : "{$label} dihapus";
        }

        if ($modelLabel === 'Rental') {
            $invoice = Arr::get($oldValues, 'invoice_number');
            $customer = Arr::get($oldValues, 'customer') ?? Arr::get($oldValues, 'customer_name');
            $parts = [];
            if ($invoice) $parts[] = "invoice {$invoice}";
            if ($customer) $parts[] = "customer {$customer}";
            $extra = $parts ? ' (' . implode(', ', $parts) . ')' : '';
            return "Penyewaan{$extra} dihapus permanently";
        }

        return "{$label} dihapus";
    }

    private static function updateDescription(string $modelLabel, ?int $modelId, array $oldValues, array $newValues, string $action): string
    {
        $label = $modelId ? "{$modelLabel} #{$modelId}" : $modelLabel;

        $changes = self::computeChanges($oldValues, $newValues);

        if (empty($changes)) {
            return self::genericDescription($modelLabel, $modelId, $action);
        }

        $changeText = self::formatChanges($changes, $modelLabel);

        if ($modelLabel === 'Product') {
            $name = Arr::get($newValues, 'name') ?? Arr::get($oldValues, 'name');
            $productLabel = $name ? "Produk '{$name}'" : $label;
            return "{$productLabel} diperbarui: {$changeText}";
        }

        if ($modelLabel === 'Rental') {
            $invoice = Arr::get($newValues, 'invoice_number') ?? Arr::get($oldValues, 'invoice_number');
            $rentalLabel = $invoice ? "Penyewaan #{$invoice}" : $label;
            return "{$rentalLabel} diubah: {$changeText}";
        }

        if ($modelLabel === 'Customer') {
            $name = Arr::get($newValues, 'name') ?? Arr::get($oldValues, 'name');
            $customerLabel = $name ? "Pelanggan '{$name}'" : $label;
            return "{$customerLabel} diubah: {$changeText}";
        }

        if ($modelLabel === 'User') {
            $name = Arr::get($newValues, 'name') ?? Arr::get($oldValues, 'name');
            $userLabel = $name ? "User '{$name}'" : $label;
            return "{$userLabel} diubah: {$changeText}";
        }

        return "{$label} diubah: {$changeText}";
    }

    private static function computeChanges(array $oldValues, array $newValues): array
    {
        $changes = [];

        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allKeys as $key) {
            if (in_array($key, ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $oldVal = Arr::get($oldValues, $key);
            $newVal = Arr::get($newValues, $key);

            if ($oldVal === $newVal) {
                continue;
            }

            if (is_array($oldVal) || is_array($newVal)) {
                $oldEncoded = json_encode($oldVal, JSON_UNESCAPED_UNICODE);
                $newEncoded = json_encode($newVal, JSON_UNESCAPED_UNICODE);
                if ($oldEncoded === $newEncoded) {
                    continue;
                }
            }

            $changes[] = [
                'field' => $key,
                'old'   => $oldVal,
                'new'   => $newVal,
            ];
        }

        return $changes;
    }

    private static function formatChanges(array $changes, string $modelLabel): string
    {
        $display = [];

        foreach ($changes as $change) {
            $field = self::humanizeField($change['field']);
            $old = self::formatValue($change['old']);
            $new = self::formatValue($change['new']);

            $display[] = "{$field} dari '{$old}' menjadi '{$new}'";
        }

        return implode(', ', array_slice($display, 0, 4));
    }

    private static function humanizeField(string $field): string
    {
        $map = [
            'stock_available'   => 'Stok tersedia',
            'stock_total'       => 'Stok total',
            'stock_note'        => 'Catatan stok',
            'rental_status'     => 'Status penyewaan',
            'payment_status'    => 'Status pembayaran',
            'fine_status'       => 'Status denda',
            'paid_amount'       => 'Total dibayar',
            'remaining_amount'  => 'Sisa tagihan',
            'late_fee'          => 'Denda',
            'fine_amount'       => 'Total denda',
            'fine_paid_amount'  => 'Denda dibayar',
            'total_amount'      => 'Total',
            'subtotal'          => 'Subtotal',
            'discount'          => 'Diskon',
            'deposit'           => 'Deposit',
            'change_amount'     => 'Kembalian',
            'price'             => 'Harga',
            'price_per_day'     => 'Harga per hari',
            'quantity'          => 'Kuantitas',
            'status'            => 'Status',
            'is_active'         => 'Status aktif',
            'name'              => 'Nama',
            'email'             => 'Email',
            'phone'             => 'No. HP',
            'address'           => 'Alamat',
            'notes'             => 'Catatan',
            'method'            => 'Metode',
            'type'              => 'Tipe',
            'amount'            => 'Jumlah',
            'invoice_number'    => 'Nomor invoice',
            'return_due_date'   => 'Tanggal jatuh tempo',
            'actual_return_date'=> 'Tanggal kembali',
            'rental_date'       => 'Tanggal sewa',
            'returned_by'       => 'Dikembalikan oleh',
            'created_by'        => 'Dibuat oleh',
            'branch_id'         => 'Cabang',
            'category_id'       => 'Kategori',
            'product_id'        => 'Produk',
            'customer_id'       => 'Pelanggan',
            'overdue_days'      => 'Hari terlambat',
            'duration_days'     => 'Durasi (hari)',
            'return_condition'  => 'Kondisi pengembalian',
            'damage_fee'        => 'Biaya kerusakan',
            'is_returned'       => 'Status pengembalian',
            'qr_code'           => 'QR Code',
            'code'              => 'Kode',
            'photo'             => 'Foto',
            'role'              => 'Role',
        ];

        return $map[$field] ?? Str::of($field)->replace('_', ' ')->title()->__toString();
    }

    private static function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_numeric($value) && $value >= 1000) {
            return 'Rp ' . number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    }

    public static function whitelistFor(string $modelLabel): array
    {
        return match ($modelLabel) {
            'Product'  => ['name', 'rental_price', 'stock_available', 'stock_total', 'status', 'stock_note', 'code'],
            'Rental'   => ['rental_status', 'payment_status', 'fine_status', 'paid_amount', 'remaining_amount', 'change_amount', 'late_fee', 'fine_amount', 'fine_paid_amount', 'total_amount', 'subtotal', 'discount', 'notes', 'overdue_days', 'payment_method'],
            'Customer' => ['name', 'phone', 'email', 'address', 'is_active'],
            'User'     => ['name', 'email', 'role', 'branch_id', 'is_active'],
            default    => [],
        };
    }

    public static function normalizeValues(mixed $values): ?array
    {
        if (is_array($values)) {
            return $values;
        }

        if (!is_string($values) || trim($values) === '') {
            return null;
        }

        $decoded = json_decode($values, true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function getDisplayableChanges(?string $action, ?string $modelType, ?array $oldValues, ?array $newValues): array
    {
        if (empty($oldValues) && empty($newValues)) {
            return [];
        }

        $modelLabel = $modelType ? class_basename($modelType) : 'Data';

        $allowedFields = self::whitelistFor($modelLabel);

        if (empty($allowedFields)) {
            return [];
        }

        $changes = [];
        foreach ($allowedFields as $field) {
            $oldVal = is_array($oldValues) ? ($oldValues[$field] ?? null) : null;
            $newVal = is_array($newValues) ? ($newValues[$field] ?? null) : null;

            if ($oldVal === $newVal) {
                continue;
            }

            if (is_array($oldVal) && is_array($newVal) && json_encode($oldVal) === json_encode($newVal)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'old'   => $oldVal,
                'new'   => $newVal,
            ];
        }

        return $changes;
    }
}
