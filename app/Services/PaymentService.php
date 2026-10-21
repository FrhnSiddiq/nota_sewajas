<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Payment;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private function assertReversible(Rental $invoice, Payment $payment): void
    {
        if ($payment->rental_id !== $invoice->id || (float) $payment->amount <= 0 ||
            $invoice->payments()->where('reference_number', $payment->payment_number)->where('amount', '<', 0)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Pembayaran ini tidak dapat dibatalkan atau dikembalikan lagi.']);
        }
    }

    public function create(Rental $invoice, array $data): Payment
    {
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($invoice, $validated) {
            $now = now();
            $payment = Payment::create([
                'rental_id' => $invoice->id,
                'received_by' => Auth::id(),
                'payment_number' => app(RentalService::class)->generatePaymentNumber(),
                'amount' => (float) $validated['amount'],
                'method' => $validated['method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'type' => 'rental',
                'notes' => $validated['notes'] ?? null,
                'paid_at' => $now,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'branch_id' => Auth::user()?->branch_id,
                'action' => 'create_payment',
                'model_type' => Rental::class,
                'model_id' => $invoice->id,
                'description' => \App\Services\ActivityLogFormatter::format(null, $payment->getAttributes(), Rental::class, $invoice->id, 'create_payment'),
                'old_values' => [],
                'new_values' => $payment->getAttributes(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            app(\App\Services\RentalService::class)->recalculatePaymentStatus($invoice->refresh());

            return $payment;
        });
    }

    public function update(Rental $invoice, Payment $payment, array $data): void
    {
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        DB::transaction(function () use ($invoice, $payment, $validated) {
            $now = now();
            $old = $payment->getAttributes();

            $payment->update([
                'amount' => (float) $validated['amount'],
                'method' => $validated['method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => $now,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'branch_id' => Auth::user()?->branch_id,
                'action' => 'update_payment',
                'model_type' => Rental::class,
                'model_id' => $invoice->id,
                'description' => \App\Services\ActivityLogFormatter::format($old, $payment->getAttributes(), Rental::class, $invoice->id, 'update_payment'),
                'old_values' => $old,
                'new_values' => $payment->getAttributes(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            app(\App\Services\RentalService::class)->recalculatePaymentStatus($invoice->refresh());
        });
    }

    public function delete(Rental $invoice, Payment $payment): void
    {
        if (in_array($invoice->rental_status, ['waiting', 'active', 'overdue'], true)) {
            throw new \InvalidArgumentException('Pembayaran tidak dapat dihapus karena penyewaan masih dalam status aktif.');
        }

        DB::transaction(function () use ($invoice, $payment) {
            $old = $payment->getAttributes();

            $payment->delete();

            ActivityLog::create([
                'user_id' => Auth::id(),
                'branch_id' => Auth::user()?->branch_id,
                'action' => 'delete_payment',
                'model_type' => Rental::class,
                'model_id' => $invoice->id,
                'description' => \App\Services\ActivityLogFormatter::format($old, null, Rental::class, $invoice->id, 'delete_payment'),
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            app(\App\Services\RentalService::class)->recalculatePaymentStatus($invoice->refresh());
        });
    }

    public function voidPayment(Rental $invoice, Payment $payment): void
    {
        DB::transaction(function () use ($invoice, $payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->assertReversible($invoice, $payment);
            // Void recorded as Payment record with negative amount
            $refundAmount = (float) $payment->amount;
            $now = now();

            $void = Payment::create([
                'rental_id' => $invoice->id,
                'received_by' => Auth::id(),
                'payment_number' => 'VOID-' . \Illuminate\Support\Str::uuid(),
                'amount' => 0 - $refundAmount,
                'method' => 'other',
                'reference_number' => $payment->payment_number,
                'type' => $payment->type,
                'notes' => 'Void payment',
                'paid_at' => $now,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'branch_id' => Auth::user()?->branch_id,
                'action' => 'void_payment',
                'model_type' => Rental::class,
                'model_id' => $invoice->id,
                'description' => \App\Services\ActivityLogFormatter::format($payment->getAttributes(), $void->getAttributes(), Rental::class, $invoice->id, 'void_payment'),
                'old_values' => $payment->getAttributes(),
                'new_values' => $void->getAttributes(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            app(\App\Services\RentalService::class)->recalculatePaymentStatus($invoice->refresh());
        });
    }

    public function refundPayment(Rental $invoice, Payment $payment): void
    {
        DB::transaction(function () use ($invoice, $payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->assertReversible($invoice, $payment);
            $refundAmount = (float) $payment->amount;
            $now = now();

            $refund = Payment::create([
                'rental_id' => $invoice->id,
                'received_by' => Auth::id(),
                'payment_number' => 'REFUND-' . \Illuminate\Support\Str::uuid(),
                'amount' => 0 - $refundAmount,
                'method' => 'other',
                'reference_number' => $payment->payment_number,
                'type' => 'refund',
                'notes' => 'Refund payment',
                'paid_at' => $now,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'branch_id' => Auth::user()?->branch_id,
                'action' => 'refund_payment',
                'model_type' => Rental::class,
                'model_id' => $invoice->id,
                'description' => \App\Services\ActivityLogFormatter::format($payment->getAttributes(), $refund->getAttributes(), Rental::class, $invoice->id, 'refund_payment'),
                'old_values' => $payment->getAttributes(),
                'new_values' => $refund->getAttributes(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            app(\App\Services\RentalService::class)->recalculatePaymentStatus($invoice->refresh());
        });
    }
}

