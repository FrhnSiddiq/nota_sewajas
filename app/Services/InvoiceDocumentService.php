<?php

namespace App\Services;

use App\Models\Rental;

/**
 * Membangun payload invoice dari data live Rental dengan kontrak yang SAMA
 * persis seperti items_snapshot / payments_snapshot / guarantees_snapshot
 * pada RentalHistory, sehingga resources/views/rental_histories/invoice.blade.php
 * dapat dipakai baik untuk halaman riwayat (arsip snapshot) maupun detail
 * penyewaan (data live) tanpa perbedaan format dokumen.
 *
 * Bentuk array snapshot di bawah ini mengikuti RentalService::createRentalHistory()
 * secara sengaja disalin (bukan direfactor) agar alur arsip yang sudah berjalan
 * tidak tersentuh perubahan.
 */
class InvoiceDocumentService
{
    /**
     * Payload siap-render untuk view rental_histories/invoice.blade.php
     * dari rental yang masih hidup (belum/waktu selesai).
     */
    public function forRental(Rental $rental): object
    {
        $rental->load([
            'customer',
            'branch',
            'createdBy',
            'returnedBy',
            'items.product.category',
            'payments',
            'guarantees',
        ]);

        return (object) [
            'invoice_number'     => $rental->invoice_number,
            'rental_status'      => $rental->rental_status,
            'rental_date'        => $rental->rental_date,
            'return_due_date'    => $rental->return_due_date,
            'actual_return_date' => $rental->actual_return_date,
            'duration_days'      => $rental->duration_days,
            'subtotal'           => $rental->subtotal,
            'discount'           => $rental->discount,
            'late_fee'           => $rental->late_fee,
            'total_amount'       => $rental->total_amount,
            'paid_amount'        => $rental->paid_amount,
            'fine_amount'        => $rental->fine_amount,
            'fine_paid_amount'   => $rental->fine_paid_amount,
            'notes'              => $rental->notes,
            // Riwayat memakai completed_at; pada rental hidup waktu selesai
            // adalah tanggal pengembalian aktual (null bila belum kembali).
            'completed_at'       => $rental->actual_return_date,
            'customer'           => $rental->customer,
            'branch'             => $rental->branch,
            'createdBy'          => $rental->createdBy,
            'returnedBy'         => $rental->returnedBy,
            'items_snapshot'     => $this->itemsSnapshot($rental),
            'payments_snapshot'  => $this->paymentsSnapshot($rental),
            'guarantees_snapshot'=> $this->guaranteesSnapshot($rental),
        ];
    }

    protected function itemsSnapshot(Rental $rental): array
    {
        return $rental->items->map(function ($item) {
            $product = $item->product;

            return [
                'rental_item_id'  => $item->id,
                'product_id'      => $item->product_id,
                'product_name'    => $item->product_name,
                'product_size'    => $item->product_size,
                'product_color'   => $item->product_color,
                'quantity'        => $item->quantity,
                'price_per_day'   => (float) $item->price_per_day,
                'duration_days'   => $item->duration_days,
                'subtotal'        => (float) $item->subtotal,
                'return_condition'=> $item->return_condition,
                'damage_fee'      => (float) ($item->damage_fee ?? 0),
                'return_notes'    => $item->return_notes,
                'is_returned'     => $item->is_returned,
                'returned_at'     => $item->returned_at?->toIso8601String(),
                'product_snapshot'=> $product ? [
                    'id'           => $product->id,
                    'code'         => $product->code,
                    'name'         => $product->name,
                    'category'     => $product->category?->name,
                    'brand'        => $product->brand,
                    'color'        => $product->color,
                    'rental_price' => (float) $product->rental_price,
                    'condition'    => $product->condition,
                ] : null,
            ];
        })->values()->toArray();
    }

    protected function paymentsSnapshot(Rental $rental): array
    {
        return $rental->payments->map(function ($payment) {
            return [
                'id'             => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount'         => (float) $payment->amount,
                'method'         => $payment->method,
                'method_label'   => $payment->method_label,
                'type'           => $payment->type,
                'paid_at'        => $payment->paid_at?->toIso8601String(),
                'notes'          => $payment->notes,
            ];
        })->values()->toArray();
    }

    protected function guaranteesSnapshot(Rental $rental): array
    {
        return $rental->guarantees->map(function ($guarantee) {
            return [
                'id'             => $guarantee->id,
                'type'           => $guarantee->type,
                'type_label'     => $guarantee->type_label ?? $guarantee->type,
                'id_number'      => $guarantee->id_number,
                'id_name'        => $guarantee->id_name,
                'deposit_amount' => (float) ($guarantee->deposit_amount ?? 0),
                'status'         => $guarantee->status,
                'description'    => $guarantee->description,
                'returned_at'    => $guarantee->returned_at?->toIso8601String(),
            ];
        })->values()->toArray();
    }
}
