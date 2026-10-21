<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RentalHistory extends Model
{
    protected $fillable = [
        'rental_id',
        'branch_id',
        'customer_id',
        'created_by',
        'returned_by',
        'invoice_number',
        'rental_date',
        'return_due_date',
        'actual_return_date',
        'duration_days',
        'subtotal',
        'discount',
        'late_fee',
        'total_amount',
        'paid_amount',
        'fine_amount',
        'fine_paid_amount',
        'payment_status',
        'fine_status',
        'notes',
        'items_snapshot',
        'guarantees_snapshot',
        'payments_snapshot',
        'completed_at',
    ];

    protected $casts = [
        'rental_date'        => 'date',
        'return_due_date'    => 'date',
        'actual_return_date' => 'date',
        'subtotal'           => 'decimal:2',
        'discount'           => 'decimal:2',
        'late_fee'           => 'decimal:2',
        'total_amount'       => 'decimal:2',
        'paid_amount'        => 'decimal:2',
        'fine_amount'        => 'decimal:2',
        'fine_paid_amount'   => 'decimal:2',
        'items_snapshot'     => 'array',
        'guarantees_snapshot' => 'array',
        'payments_snapshot'  => 'array',
        'completed_at'       => 'datetime',
    ];

    // ─── RELATIONSHIPS ────────────────────────────────────────────────────

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid'    => 'Selesai',
            'partial' => 'Sebagian',
            'unpaid'  => 'Belum Bayar',
            default   => ucfirst($this->payment_status ?? '-'),
        };
    }

    // ─── ACCESSORS ────────────────────────────────────────────────────────

    protected function itemsSnapshot(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
        );
    }

    protected function guaranteesSnapshot(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
        );
    }

    protected function paymentsSnapshot(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
        );
    }
}