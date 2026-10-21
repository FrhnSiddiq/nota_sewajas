<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalHistory;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Guarantee;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;

class RentalService
{
    public function updatePayment(Rental $rental, \App\Models\Payment $payment, array $data): void
    {
        app(\App\Services\PaymentService::class)->update($rental, $payment, $data);
        $rental->refresh();
        $this->recalculatePaymentStatus($rental);
    }

    public function deletePayment(Rental $rental, \App\Models\Payment $payment): void
    {
        app(\App\Services\PaymentService::class)->delete($rental, $payment);
        $rental->refresh();
        $this->recalculatePaymentStatus($rental);
    }

    public function refundPayment(Rental $rental, \App\Models\Payment $payment): void
    {
        app(\App\Services\PaymentService::class)->refundPayment($rental, $payment);
        $rental->refresh();
        $this->recalculatePaymentStatus($rental);
    }

    public function voidPayment(Rental $rental, \App\Models\Payment $payment): void
    {
        app(\App\Services\PaymentService::class)->voidPayment($rental, $payment);
        $rental->refresh();
        $this->recalculatePaymentStatus($rental);
    }

    public function recordRefund(Rental $rental, float $amount, ?string $notes = null): void
    {
        $rental->refresh();
        $this->recalculatePaymentStatus($rental);
        $rental->refresh();

        $currentOverpayment = (float) ($rental->change_amount ?? 0);

        if ($amount > $currentOverpayment) {
            throw new \InvalidArgumentException(
                "Refund amount (Rp" . number_format($amount, 0, ',', '.') . ") exceeds available overpayment (Rp" . number_format($currentOverpayment, 0, ',', '.') . ")"
            );
        }

        $oldValues = $this->rentalSnapshot($rental);

        DB::transaction(function () use ($rental, $amount, $notes, $oldValues) {
            Payment::create([
                'rental_id'        => $rental->id,
                'received_by'      => Auth::id(),
                'payment_number'   => 'REFUND-' . now()->format('YmdHis'),
                'amount'           => 0 - $amount,
                'method'           => 'other',
                'type'             => 'refund',
                'notes'            => $notes ?? 'Kembalian overpayment',
                'paid_at'          => now(),
            ]);

            $this->recalculatePaymentStatus($rental);
            $newValues = $this->rentalSnapshot($rental);

            $this->logActivity('record_refund', $rental, $oldValues, $newValues);
        });
    }

    public function recalculatePaymentStatus(Rental $rental): array
    {
        $oldValues = $this->rentalSnapshot($rental);

        $rentalFee = (float) $rental->total_amount;
        $fineAmount = (float) $rental->fine_amount;

        $totalPaidForRental = $this->netPaymentsForTypes($rental, ['rental', 'deposit']);
        $totalPaidForFine = $this->netPaymentsForTypes($rental, ['late_fee', 'damage_fee']);

        $remainingRental = max(0, $rentalFee - $totalPaidForRental);

        if ($totalPaidForRental <= 0) {
            $paymentStatus = Rental::PAYMENT_UNPAID;
        } elseif ($totalPaidForRental < $rentalFee) {
            $paymentStatus = Rental::PAYMENT_PARTIAL;
        } else {
            $paymentStatus = Rental::PAYMENT_PAID;
        }

        $totalReceived = (float) $rental->payments()->sum('amount');
        $grossOverpayment = max(0, $totalReceived - $rentalFee - $fineAmount);
        $changeAmount = $grossOverpayment;
        $availableForFine = max(0, $totalReceived - $rentalFee);
        $finePaidAmount = min($fineAmount, max($totalPaidForFine, $availableForFine));

        if ($fineAmount <= 0) {
            $fineStatus = Rental::FINE_NONE;
        } elseif ($finePaidAmount >= $fineAmount) {
            $fineStatus = Rental::FINE_PAID;
        } elseif ($finePaidAmount > 0) {
            $fineStatus = Rental::FINE_PARTIAL;
        } else {
            $fineStatus = Rental::FINE_UNPAID;
        }

        $rental->update([
            'paid_amount' => $totalPaidForRental,
            'payment_status' => $paymentStatus,
            'remaining_amount' => $remainingRental,
            'fine_paid_amount' => $finePaidAmount,
            'fine_status' => $fineStatus,
            'change_amount' => $changeAmount,
        ]);

        $newValues = $this->rentalSnapshot($rental);

        $this->addSalesPoints($rental, 'serah');
        $this->syncRentalHistory($rental);

        return $newValues;
    }

    public function paymentBalances(Rental $rental): array
    {
        $rentalBalance = max(
            0,
            (float) $rental->total_amount - $this->netPaymentsForTypes($rental, ['rental', 'deposit'])
        );
        $lateFeePaid = $this->netPaymentsForTypes($rental, ['late_fee']);
        $damageFeePaid = $this->netPaymentsForTypes($rental, ['damage_fee']);
        $lateFeeBalance = max(0, (float) $rental->late_fee - $lateFeePaid);
        $damageFeeAmount = (float) $rental->items()->sum('damage_fee');
        $damageFeeBalance = max(0, $damageFeeAmount - $damageFeePaid);
        $netFinePayments = max(0, $lateFeePaid + $damageFeePaid);
        $totalReceived = (float) $rental->payments()->sum('amount');
        $finePaidAmount = min(
            (float) $rental->fine_amount,
            max($netFinePayments, max(0, $totalReceived - (float) $rental->total_amount))
        );
        $totalFineBalance = max(0, (float) $rental->fine_amount - $finePaidAmount);
        $unallocatedFineCredit = max(
            0,
            max(0, $totalReceived - (float) $rental->total_amount) - $netFinePayments
        );
        $lateFeeCredit = min($lateFeeBalance, $unallocatedFineCredit);
        $lateFeeBalance -= $lateFeeCredit;
        $damageFeeBalance = max(0, $damageFeeBalance - ($unallocatedFineCredit - $lateFeeCredit));

        return [
            'rental' => $rentalBalance,
            'late_fee' => min($lateFeeBalance, $totalFineBalance),
            'damage_fee' => min($damageFeeBalance, $totalFineBalance),
        ];
    }

    private function netPaymentsForTypes(Rental $rental, array $types): float
    {
        $payments = $rental->payments()->whereIn('type', $types)->get(['payment_number', 'amount']);
        $paymentNumbers = $payments->pluck('payment_number');
        $refunds = $paymentNumbers->isEmpty()
            ? 0
            : (float) $rental->payments()
                ->where('type', 'refund')
                ->whereIn('reference_number', $paymentNumbers)
                ->sum('amount');

        return (float) $payments->sum('amount') + $refunds;
    }

    public function rentalSnapshotWhitelisted(Rental $rental): array
    {
        $whitelist = ActivityLogFormatter::whitelistFor('Rental');
        return array_intersect_key($this->rentalSnapshot($rental), array_flip($whitelist));
    }

    /**
     * Total poin produk untuk satu event serah/kembali.
     * Σ(poin produk × jumlah item). Produk yang sudah di-soft-delete dihitung 0.
     */
    public function pointsForRental(Rental $rental, string $type): int
    {
        if (!in_array($type, ['serah', 'kembali'], true)) {
            throw new \InvalidArgumentException('Invalid point type: ' . $type);
        }

        $rental->loadMissing('items.product');

        return (int) $rental->items->sum(function ($item) {
            $product = $item->product;
            // Relasi product memakai withTrashed(); produk yang sudah di-soft-delete
            // tidak memberi poin (snapshot nama/qty pada item tetap dipertahankan).
            if (!$product || $product->trashed()) {
                return 0;
            }

            return max(0, (int) $product->points) * max(0, (int) $item->quantity);
        });
    }

    public function addSalesPoints(Rental $rental, string $type, ?int $points = null): void
    {
        $guardColumn = match ($type) {
            'serah' => 'points_awarded_serah',
            'kembali' => 'points_awarded_kembali',
            default => throw new \InvalidArgumentException('Invalid point type: ' . $type),
        };

        if ($rental->{$guardColumn}) {
            return;
        }

        $sales = match ($type) {
            'serah' => $rental->createdBy,
            'kembali' => $rental->returnedBy,
        };

        if (!$sales || !$sales->isSales()) {
            return;
        }

        $points ??= $this->pointsForRental($rental, $type);

        $sales->increment('total_points', $points);
        $rental->update([$guardColumn => true]);
    }

    public function buildDetailPayload(Rental $rental): array
    {
        return [
            'id' => $rental->id,
            'invoice_number' => $rental->invoice_number,
            'rental_status' => $rental->rental_status,
            'payment_status' => $rental->payment_status,
            'rental_date' => $rental->rental_date?->format('d/m/Y') ?? '-',
            'return_due_date' => $rental->return_due_date?->format('d/m/Y') ?? '-',
            'created_by' => optional($rental->createdBy)->name,
            'subtotal' => (float) ($rental->subtotal ?? 0),
            'discount' => (float) ($rental->discount ?? 0),
            'deposit' => (float) ($rental->guarantees->where('type', 'deposit')->sum('deposit_amount') ?? 0),
            'total_amount' => (float) ($rental->total_amount ?? 0),
            'paid_amount' => (float) ($rental->paid_amount ?? 0),
            'remaining_amount' => (float) ($rental->remaining_amount ?? 0),
            'late_fee' => (float) ($rental->late_fee ?? 0),
            'fine_amount' => (float) ($rental->fine_amount ?? 0),
            'fine_paid_amount' => (float) ($rental->fine_paid_amount ?? 0),
            'fine_status' => $rental->fine_status ?? Rental::FINE_NONE,
            'payment_method' => $rental->payment_method ?? null,
            'notes' => $rental->notes ?? null,
            'change_amount' => (float) ($rental->change_amount ?? 0),
            'payment_balances' => $this->paymentBalances($rental),
            'total_owed' => max(0, (float) ($rental->total_amount ?? 0) + (float) ($rental->fine_amount ?? 0)),
            'overpayment' => (float) ($rental->change_amount ?? 0),
            'refund_given' => (float) abs($rental->payments()->where('type', 'refund')->sum('amount')),
            'customer' => [
                'name' => $rental->customer->name ?? '-',
                'phone' => $rental->customer->phone ?? '-',
            ],
            'branch' => [
                'name' => $rental->branch->name ?? '-',
            ],
            'items' => $rental->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'size' => $item->product_size,
                    'category_name' => optional($item->product?->category)->name ?? '-',
                    'quantity' => (int) $item->quantity,
                    'price' => (float) ($item->price_per_day ?? 0),
                    'subtotal' => (float) ($item->subtotal ?? (($item->price_per_day ?? 0) * ($item->quantity ?? 0))),
                    'photo' => $item->product && $item->product->photo ? asset('storage/' . $item->product->photo) : null,
                    'return_condition' => $item->return_condition,
                    'damage_fee' => (float) ($item->damage_fee ?? 0),
                    'return_notes' => $item->return_notes,
                    'is_returned' => (bool) $item->is_returned,
                ];
            })->values(),
            'guarantees' => $rental->guarantees->map(function ($guarantee) {
                return [
                    'id' => $guarantee->id,
                    'type' => $guarantee->type,
                    'type_label' => $guarantee->type_label ?? $guarantee->type,
                    'id_number' => $guarantee->id_number,
                    'deposit_amount' => (float) ($guarantee->deposit_amount ?? 0),
                    'status' => $guarantee->status,
                    'notes' => $guarantee->description ?? null,
                    'id_photo_url' => $guarantee->id_photo_url ?? null,
                ];
            })->values(),
            'payments' => $rental->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => (float) ($payment->amount ?? 0),
                    'method' => $payment->method,
                    'method_label' => $payment->method_label,
                    'paid_at' => $payment->paid_at?->format('d/m/Y H:i'),
                    'notes' => $payment->notes,
                ];
            })->values(),
            'activity_logs' => $rental->activityLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'description' => $log->description,
                    'user' => optional($log->user)->name,
                    'created_at' => $log->created_at?->format('d/m/Y H:i'),
                ];
            })->values(),
        ];
    }

    /**
     * Generate a globally unique, branch-aware, chronological invoice number.
     *
     * Format: INV{YYYYMMDD}{BB}{SSSS}
     * Example: INV20260727010001
     *
      * Race-condition safe:
      * - Uses lockForUpdate() on the last matching invoice
      * - Retry loop with up to 100 attempts per branch+date
      * - Final EXISTS check before returning
     *
     * Must be called from within a DB::transaction() with the same connection.
     *
     * @param int $branchId
     * @return string
     * @throws \RuntimeException after 100 failed attempts
     */
    public function generateInvoiceNumber(int $branchId): string
    {
        $prefix = 'INV';
        $now = now();
        $date = $now->format('Ymd');
        $branchCode = str_pad($branchId, 2, '0', STR_PAD_LEFT);
        $pattern = "{$prefix}{$date}{$branchCode}%";
        $maxAttempts = 100;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // 1. Lock the LAST matching invoice row for today + branch
            $last = Rental::query()
                ->where('invoice_number', 'like', $pattern)
                ->whereDate('created_at', $now->toDateString())
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            // 2. Extract or start sequence
            $nextSequence = $last
                ? (int) substr($last->invoice_number, -4) + 1
                : 1;

            // 3. Generate candidate number
            $candidate = $prefix . $date . $branchCode . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

            // 4. Verify uniqueness with EXISTS check
            //    (pessimistic lock above prevents concurrent inserts,
            //     but this double-check ensures correctness)
            $exists = Rental::query()
                ->where('invoice_number', $candidate)
                ->lockForUpdate()
                ->exists();

            if (!$exists) {
                return $candidate;
            }

            // 5. Collision detected — retry with next sequence
            //    This can happen if a concurrent transaction inserted
            //    the same number between our lock and check.
            //    We simply loop and try the next sequence number.
        }

        // 6. Max retries exhausted — fail hard instead of creating a duplicate
        throw new \RuntimeException(
            "Gagal membuat nomor invoice setelah {$maxAttempts} percobaan " .
            "untuk cabang {$branchId} pada tanggal {$date}. " .
            "Silakan coba lagi."
        );
    }

    public function generatePaymentNumber(): string
    {
        $prefix = 'PAY';
        $now = now();
        $date = $now->format('Ymd');
        $last = Payment::where('payment_number', 'like', "{$prefix}{$date}%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();
        $sequence = $last ? (int) substr($last->payment_number, -4) + 1 : 1;
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function createRental(array $data): Rental
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $durationDays = (int) $data['duration_days'];

            // ─── STEP 1: Collect branch IDs from all selected products via product_branch pivot table ───
            $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();

            // Get branch IDs for each product from the pivot table (with lock on products)
            $productBranchIds = [];
            foreach ($productIds as $productId) {
                $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
                $branchIds = $product->branches()->pluck('branches.id')->map(fn($id) => (int) $id)->all();
                // Fallback to legacy branch_id if no pivot entries exist
                if (empty($branchIds) && $product->branch_id) {
                    $branchIds = [(int) $product->branch_id];
                }
                $productBranchIds[] = $branchIds;
            }

            // ─── STEP 2: Find common branch across all products ───
            $commonBranchIds = array_reduce($productBranchIds, function ($carry, $item) {
                return $carry === null ? $item : array_values(array_intersect($carry, $item));
            }, null);

            if (empty($commonBranchIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ditemukan cabang yang sama untuk semua produk yang dipilih. Pastikan semua produk berasal dari cabang yang sama.',
                ]);
            }

            // ─── STEP 3: Resolve rental branch ───
            // Prefer a branch the user can access, otherwise use the first common branch
            $userAccessibleBranches = $user->isSuperAdmin() ? [] : $user->accessible_branch_ids;
            $branchId = null;

            if ($userAccessibleBranches !== []) {
                $accessibleCommon = array_values(array_intersect($commonBranchIds, $userAccessibleBranches));
                if ($accessibleCommon !== []) {
                    $branchId = $accessibleCommon[0];
                }
            }

            if ($branchId === null) {
                $branchId = $commonBranchIds[0];
            }

            // ─── STEP 4: Verify non-super-admin users can access the branch ───
            if (!$user->isSuperAdmin() && !$user->canAccessBranch((int) $branchId)) {
                throw ValidationException::withMessages([
                    'items' => 'Produk bukan berasal dari cabang yang Anda akses.',
                ]);
            }

            if ($user->isSales()) {
                $customer = Customer::find($data['customer_id']);
                if (!$customer || (int) $customer->branch_id !== (int) $branchId) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Pelanggan harus terdaftar di cabang yang sama dengan penyewaan.',
                    ]);
                }
            }
            // Calculate subtotal (using locked products from step 1)
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                $subtotal += $product->rental_price * $item['quantity'] * $durationDays;
            }

            $discount = $data['discount'] ?? 0;
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'Diskon tidak boleh melebihi subtotal.']);
            }
            $totalAmount = $subtotal - $discount;

            // ─── Generate invoice number inside the same transaction ───
            $invoiceNumber = $this->generateInvoiceNumber($branchId);

            // Create rental with resolved branchId
            $rental = Rental::create([
                'invoice_number'  => $invoiceNumber,
                'branch_id'       => $branchId,
                'customer_id'     => $data['customer_id'],
                'created_by'      => $user->id,
                'rental_date'     => $data['rental_date'],
                'return_due_date' => Carbon::parse($data['rental_date'])->addDays($durationDays),
                'duration_days'   => $durationDays,
                'subtotal'        => $subtotal,
                'discount'        => $discount,
                'total_amount'    => $totalAmount,
                'paid_amount'     => 0,
                'payment_status'  => Rental::PAYMENT_UNPAID,
                'payment_method'  => $data['payment_method'] ?? null,
                'rental_status'   => Rental::STATUS_WAITING,
                'notes'           => $data['notes'] ?? null,
            ]);

            // Create rental items & reduce stock
            foreach ($data['items'] as $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                $itemSubtotal = $product->rental_price * $item['quantity'] * $durationDays;

                RentalItem::create([
                    'rental_id'     => $rental->id,
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'product_size'  => $item['product_size'],
                    'product_color' => $product->color,
                    'quantity'      => $item['quantity'],
                    'price_per_day' => $product->rental_price,
                    'duration_days' => $durationDays,
                    'subtotal'      => $itemSubtotal,
                ]);

                // Reduce stock per-size
                $remaining = $product->decrementSizeStock($item['product_size'], $item['quantity']);
                if ($remaining < 0) {
                    throw ValidationException::withMessages([
                        'items' => "Stok ukuran {$item['product_size']} produk {$product->name} tidak mencukupi (tersedia: {$product->stock_available}, diminta: {$item['quantity']}).",
                    ]);
                }
            }

            // Create guarantee - handle flat form fields
            $guaranteeType = $data['guarantee_type'] ?? ($data['guarantee']['type'] ?? null);
            if (!empty($guaranteeType)) {
                $idPhotoPath = null;

                if ($guaranteeType === 'ktp' || $guaranteeType === 'sim') {
                    if (!empty($data['guarantee_id_photo']) && is_a($data['guarantee_id_photo'], \Illuminate\Http\UploadedFile::class)) {
                        $idPhotoPath = $data['guarantee_id_photo']->store('guarantees/id_photos', 'public');
                    }
                }

                if ($guaranteeType === 'deposit') {
                    $depositAmount = (float) ($data['guarantee_deposit'] ?? 0);
                    if ($depositAmount <= 0) {
                        abort(422, 'Jumlah deposit wajib diisi untuk jenis jaminan Deposit Uang.');
                    }
                }

                if ($guaranteeType === 'custom') {
                    $description = trim((string) ($data['guarantee_notes'] ?? ''));
                    if ($description === '') {
                        abort(422, 'Deskripsi jaminan custom wajib diisi.');
                    }
                }

                Guarantee::create([
                    'rental_id'      => $rental->id,
                    'type'           => $guaranteeType,
                    'id_number'      => $data['guarantee_id_number'] ?? ($data['guarantee']['id_number'] ?? null),
                    'id_name'        => $data['guarantee_id_name'] ?? ($data['guarantee']['id_name'] ?? null),
                    'deposit_amount' => $guaranteeType === 'deposit' ? (float) ($data['guarantee_deposit'] ?? 0) : 0,
                    'description'    => $data['guarantee_notes'] ?? ($data['guarantee']['description'] ?? null),
                    'id_photo'       => $idPhotoPath,
                    'status'         => 'held',
                ]);
            }

            // Generate QR code
            $this->generateQrCode($rental);

            // Log activity
            $this->logActivity('create_rental', $rental, [], $this->rentalSnapshot($rental));

            return $rental->fresh(['items', 'customer', 'guarantees']);
        });
    }

    public function processPayment(Rental $rental, array $data): Payment
    {
        return DB::transaction(function () use ($rental, $data) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            $this->recalculatePaymentStatus($rental);
            $rental->refresh();
            $oldValues = $this->rentalSnapshot($rental);

            $amount = $data['amount'] ?? 0;
            if (is_string($amount) && !is_numeric($amount)) {
                $amount = preg_replace('/[^0-9]/', '', $amount);
            }
            $amount = (float) $amount;
            $now = now();
            $paymentType = $data['payment_type'] ?? 'rental';
            $paymentTypeLabels = [
                'rental' => 'pembayaran sewa',
                'late_fee' => 'denda telat',
                'damage_fee' => 'denda kerusakan',
            ];

            if (!array_key_exists($paymentType, $paymentTypeLabels)) {
                throw ValidationException::withMessages([
                    'payment_type' => 'Tipe pembayaran yang dipilih tidak valid.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran harus lebih dari Rp0.',
                ]);
            }

            $paymentBalance = $this->paymentBalances($rental)[$paymentType];
            if ($paymentBalance <= 0) {
                throw ValidationException::withMessages([
                    'payment_type' => 'Tidak ada sisa tagihan untuk ' . $paymentTypeLabels[$paymentType] . '.',
                ]);
            }
            if ($amount > $paymentBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran melebihi sisa ' . $paymentTypeLabels[$paymentType]
                        . '. Maksimal yang dapat dibayar: Rp' . number_format($paymentBalance, 0, ',', '.') . '.',
                ]);
            }

            $recentPayment = Payment::where('rental_id', $rental->id)
                ->where('amount', $amount)
                ->where('method', $data['method'])
                ->where('type', $paymentType)
                ->where('created_at', '>=', $now->copy()->subSeconds(5))
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            if ($recentPayment) {
                throw new \RuntimeException('Pembayaran yang sama sudah diproses 2 detik yang lalu. Jika ini tidak benar, coba lagi dalam beberapa saat.');
            }

            $payment = Payment::create([
                'rental_id'        => $rental->id,
                'received_by'      => Auth::id(),
                'payment_number'   => $this->generatePaymentNumber(),
                'amount'           => $amount,
                'method'           => $data['method'],
                'reference_number' => $data['reference_number'] ?? null,
                'type'             => $paymentType,
                'notes'            => $data['notes'] ?? null,
                'paid_at'          => $now,
            ]);

            $this->recalculatePaymentStatus($rental);
            $newValues = $this->rentalSnapshot($rental);

            $this->logActivity('process_payment', $rental, $oldValues, $newValues);

            $this->addSalesPoints($rental, 'serah');

            return $payment;
        });
    }

    public function processReturn(Rental $rental, array $data = []): Rental
    {
        $oldValues = $this->rentalSnapshot($rental);

        return DB::transaction(function () use ($rental, $data, $oldValues) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            if (!in_array($rental->rental_status, [Rental::STATUS_ACTIVE, Rental::STATUS_OVERDUE], true)) {
                throw ValidationException::withMessages(['rental_status' => 'Hanya penyewaan aktif yang dapat dikembalikan.']);
            }
            
            // Validasi: Pembayaran harus lunas sebelum pengembalian
            if ($rental->payment_status !== Rental::PAYMENT_PAID) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Penyewaan belum dapat diselesaikan karena pembayaran belum lunas. Silakan lunasi pembayaran terlebih dahulu.'
                ]);
            }
            $now = now();
            $lateFee = 0;
            $overdueDays = 0;
            $totalDamageFees = 0;

            if ($rental->return_due_date->lt($now->copy()->startOfDay())) {
                $overdueDays = (int) $rental->return_due_date->diffInDays($now->copy()->startOfDay());
                $dailyRate = $rental->subtotal / $rental->duration_days;
                $lateFee = (float) round($dailyRate * $overdueDays * 0.5);
            }

            foreach ($rental->items as $item) {
                if ($item->is_returned) {
                    continue;
                }
                $returnData = collect($data['items'] ?? [])
                    ->firstWhere('rental_item_id', $item->id);

                $condition = $returnData['condition'] ?? 'baik';
                $damageFee = $returnData['damage_fee'] ?? 0;
                $totalDamageFees += $damageFee;

                $item->update([
                    'is_returned'      => true,
                    'returned_at'      => $now,
                    'return_condition' => $condition,
                    'damage_fee'       => $damageFee,
                    'return_notes'     => $returnData['notes'] ?? null,
                ]);

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if ($product) {
                    if (in_array($condition, ['rusak_ringan', 'rusak_berat', 'hilang'], true)) {
                        $productSizeId = $product->sizes()->where('size', $item->product_size)->value('id');

                        if ($productSizeId === null) {
                            // Produk tidak punya baris product_sizes untuk ukuran ini
                            // (produk legacy / ukuran tidak terdaftar). product_size_id
                            // NOT NULL sehingga record tidak bisa dibuat. Stok sengaja
                            // tidak diubah agar unit rusak tidak ikut kembali tersedia;
                            // admin perlu memperbaiki data ukuran produk.
                            ActivityLog::create([
                                'user_id'    => Auth::id(),
                                'branch_id'  => Auth::user()?->branch_id,
                                'action'     => 'product_size_missing_return',
                                'model_type' => Rental::class,
                                'model_id'   => $rental->id,
                                'description' => "Ukuran [{$item->product_size}] tidak memiliki data stok pada produk ID {$item->product_id} saat pengembalian item ID {$item->id} (rental {$rental->invoice_number}). Item rusak/hilang tidak dicatat ke menu maintenance dan stok tidak diperbarui.",
                            ]);
                        } else {
                            $product->incrementMaintenanceStock($item->product_size, $item->quantity, $condition);
                            \App\Models\ProductStockMaintenance::create([
                                'product_id'      => $product->id,
                                'product_size_id' => $productSizeId,
                                'rental_id'       => $rental->id,
                                'rental_item_id'  => $item->id,
                                'size'            => $item->product_size,
                                'condition'       => $condition,
                                'quantity'        => $item->quantity,
                                'damage_fee'      => $damageFee,
                                'status'          => 'maintenance',
                                'notes'           => $returnData['notes'] ?? null,
                                'created_by'      => Auth::id(),
                            ]);
                        }
                    } else {
                        $product->incrementSizeStock($item->product_size, $item->quantity);
                    }
                } else {
                    ActivityLog::create([
                        'user_id'   => Auth::id(),
                        'branch_id' => Auth::user()?->branch_id,
                        'action'    => 'product_not_found_return',
                        'model_type' => Rental::class,
                        'model_id'   => $rental->id,
                        'description' => "Produk ID {$item->product_id} tidak ditemukan di database saat memproses pengembalian item ID {$item->id} (rental {$rental->invoice_number}). Stok tidak diperbarui untuk item ini.",
                    ]);
                }
            }

            $rental->guarantees()->update(['status' => 'returned', 'returned_at' => $now]);

            $fineAmount = (float) round($lateFee + $totalDamageFees);
            $rental->update([
                'rental_status'      => Rental::STATUS_RETURNED,
                'actual_return_date' => $now->toDateString(),
                'returned_at'        => $now,
                'returned_by'        => Auth::id(),
                'overdue_days'       => $overdueDays,
                'late_fee'           => $lateFee,
                'total_amount'       => $rental->total_amount,
                'fine_amount'        => $fineAmount,
                'fine_status'        => $fineAmount > 0 ? Rental::FINE_UNPAID : Rental::FINE_NONE,
            ]);

            $this->recalculatePaymentStatus($rental);
            $newValues = $this->rentalSnapshot($rental);

            $this->logActivity('return_rental', $rental, $oldValues, $newValues);

            $this->addSalesPoints($rental, 'kembali');

            $this->syncRentalHistory($rental);

            return $rental->fresh();
        });
    }

    protected function createRentalHistory(Rental $rental, \Carbon\Carbon $completedAt): void
    {
        $rental->load(['items.product.category', 'guarantees', 'payments']);

        // Build items snapshot with full product details
        $itemsSnapshot = $rental->items->map(function ($item) {
            $product = $item->product;
            return [
                'rental_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_size' => $item->product_size,
                'product_color' => $item->product_color,
                'quantity' => $item->quantity,
                'price_per_day' => (float) $item->price_per_day,
                'duration_days' => $item->duration_days,
                'subtotal' => (float) $item->subtotal,
                'return_condition' => $item->return_condition,
                'damage_fee' => (float) ($item->damage_fee ?? 0),
                'return_notes' => $item->return_notes,
                'is_returned' => $item->is_returned,
                'returned_at' => $item->returned_at?->toIso8601String(),
                // Product snapshot
                'product_snapshot' => $product ? [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'brand' => $product->brand,
                    'color' => $product->color,
                    'rental_price' => (float) $product->rental_price,
                    'condition' => $product->condition,
                ] : null,
            ];
        })->values()->toArray();

        // Build guarantees snapshot
        $guaranteesSnapshot = $rental->guarantees->map(function ($guarantee) {
            return [
                'id' => $guarantee->id,
                'type' => $guarantee->type,
                'type_label' => $guarantee->type_label ?? $guarantee->type,
                'id_number' => $guarantee->id_number,
                'id_name' => $guarantee->id_name,
                'deposit_amount' => (float) ($guarantee->deposit_amount ?? 0),
                'status' => $guarantee->status,
                'description' => $guarantee->description,
                'returned_at' => $guarantee->returned_at?->toIso8601String(),
            ];
        })->values()->toArray();

        // Build payments snapshot
        $paymentsSnapshot = $rental->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'method_label' => $payment->method_label,
                'type' => $payment->type,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'notes' => $payment->notes,
            ];
        })->values()->toArray();

        RentalHistory::updateOrCreate(
            ['rental_id' => $rental->id],
            [
                'branch_id'           => $rental->branch_id,
                'customer_id'         => $rental->customer_id,
                'created_by'          => $rental->created_by,
                'returned_by'         => $rental->returned_by,
                'invoice_number'      => $rental->invoice_number,
                'rental_date'         => $rental->rental_date,
                'return_due_date'     => $rental->return_due_date,
                'actual_return_date'  => $rental->actual_return_date,
                'duration_days'       => $rental->duration_days,
                'subtotal'            => $rental->subtotal,
                'discount'            => $rental->discount,
                'late_fee'            => $rental->late_fee,
                'total_amount'        => $rental->total_amount,
                'paid_amount'         => $rental->paid_amount,
                'fine_amount'         => $rental->fine_amount,
                'fine_paid_amount'    => $rental->fine_paid_amount,
                'payment_status'      => $rental->payment_status,
                'fine_status'         => $rental->fine_status,
                'notes'               => $rental->notes,
                'items_snapshot'      => $itemsSnapshot,
                'guarantees_snapshot' => $guaranteesSnapshot,
                'payments_snapshot'   => $paymentsSnapshot,
                'completed_at'        => $completedAt,
            ]
        );
    }

    protected function syncRentalHistory(Rental $rental): void
    {
        $isComplete = $rental->isCompleted();

        $history = $rental->history()->first();
        if (!$isComplete) {
            $history?->delete();
            return;
        }

        $historyWasComplete = $history
            && $history->payment_status === Rental::PAYMENT_PAID
            && in_array($history->fine_status, [Rental::FINE_NONE, Rental::FINE_PAID], true)
            && (float) $history->fine_amount <= (float) $history->fine_paid_amount;
        $completedAt = $historyWasComplete
            ? ($history->completed_at ?? now())
            : now();

        $this->createRentalHistory($rental, $completedAt);
    }

    public function generateQrCode(Rental $rental): void
    {
        $qrData = route('rentals.scan', $rental->invoice_number);
        $path = 'qrcodes/rentals/' . $rental->invoice_number . '.svg';
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $svg = QrCode::format('svg')->size(200)->generate($qrData);
        file_put_contents($fullPath, $svg);

        $rental->update(['qr_code' => $path]);
    }

    public function getQrUrl(Rental $rental): string
    {
        return route('rentals.scan', $rental->invoice_number);
    }

    public function updateOverdueRentals(): int
    {
        return Rental::where('rental_status', Rental::STATUS_ACTIVE)
            ->where('return_due_date', '<', now()->toDateString())
            ->update(['rental_status' => Rental::STATUS_OVERDUE]);
    }

    protected function rentalSnapshot(Rental $rental): array
    {
        return [
            'id'                     => $rental->id,
            'invoice_number'         => $rental->invoice_number,
            'rental_status'          => $rental->rental_status,
            'payment_status'         => $rental->payment_status,
            'fine_status'            => $rental->fine_status,
            'paid_amount'            => (float) ($rental->paid_amount ?? 0),
            'remaining_amount'       => (float) ($rental->remaining_amount ?? 0),
            'fine_paid_amount'       => (float) ($rental->fine_paid_amount ?? 0),
            'fine_amount'            => (float) ($rental->fine_amount ?? 0),
            'late_fee'               => (float) ($rental->late_fee ?? 0),
            'total_amount'           => (float) ($rental->total_amount ?? 0),
            'change_amount'          => (float) ($rental->change_amount ?? 0),
            'overdue_days'           => (int) ($rental->overdue_days ?? 0),
            'subtotal'               => (float) ($rental->subtotal ?? 0),
            'discount'               => (float) ($rental->discount ?? 0),
            'notes'                  => $rental->notes ?? null,
            'payment_method'         => $rental->payment_method ?? null,
            'returned_by'            => $rental->returned_by,
            'actual_return_date'     => $rental->actual_return_date?->format('Y-m-d'),
            'return_due_date'        => $rental->return_due_date?->format('Y-m-d'),
            'rental_date'            => $rental->rental_date?->format('Y-m-d'),
            'customer'               => optional($rental->customer)->name ?? '-',
        ];
    }

    protected function logActivity(string $action, Rental $rental, ?array $oldValues = null, ?array $newValues = null): void
    {
        $description = \App\Services\ActivityLogFormatter::format(
            $oldValues,
            $newValues,
            Rental::class,
            $rental->id,
            $action
        );

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'branch_id'   => Auth::user()?->branch_id,
            'action'      => $action,
            'model_type'  => Rental::class,
            'model_id'    => $rental->id,
            'description' => $description,
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
