<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property \Illuminate\Support\Carbon $rental_date
 * @property \Illuminate\Support\Carbon $return_due_date
 * @property \Illuminate\Support\Carbon|null $actual_return_date
 */
class Rental extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'branch_id', 'customer_id', 'created_by', 'returned_by',
        'rental_date', 'return_due_date', 'actual_return_date', 'duration_days',
        'subtotal', 'discount', 'late_fee', 'total_amount', 'paid_amount', 'change_amount',
        'payment_status', 'payment_method', 'rental_status', 'qr_code', 'notes',
        'returned_at',
        'return_condition', 'return_notes', 'overdue_days',
        'fine_status', 'fine_amount', 'fine_paid_amount',
        'points_awarded_serah', 'points_awarded_kembali',
    ];

    protected $casts = [
        'rental_date'        => 'date',
        'return_due_date'    => 'date',
        'actual_return_date' => 'date',
        'cancelled_at'       => 'datetime',
        'returned_at'        => 'datetime',
        'subtotal'           => 'decimal:2',
        'discount'           => 'decimal:2',
        'late_fee'           => 'decimal:2',
        'total_amount'       => 'decimal:2',
        'paid_amount'        => 'decimal:2',
        'change_amount'      => 'decimal:2',
        'fine_amount'        => 'decimal:2',
        'fine_paid_amount'   => 'decimal:2',
    ];

    const STATUS_WAITING   = 'waiting';
    const STATUS_ACTIVE    = 'active';
    const STATUS_OVERDUE   = 'overdue';
    const STATUS_RETURNED  = 'returned';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_UNPAID  = 'unpaid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_PAID    = 'paid';

    const FINE_NONE    = 'none';
    const FINE_UNPAID  = 'unpaid';
    const FINE_PARTIAL = 'partial';
    const FINE_PAID    = 'paid';

    // ─── RELATIONSHIPS ────────────────────────────────────────────────────

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

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    /** @return HasMany<Guarantee, $this> */
    public function guarantees(): HasMany
    {
        return $this->hasMany(Guarantee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function history(): HasOne
    {
        return $this->hasOne(RentalHistory::class);
    }

    /**
     * Relasi ke tabel rental_returns (detail pengembalian).
     */
    public function returnRecord(): HasOne
    {
        return $this->hasOne(RentalReturn::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'model_id')->where('model_type', self::class);
    }

    public function statusHistories()
    {
        return $this->morphMany(StatusHistory::class, 'model')
            ->where('model_type', self::class);
    }


    // ─── ACCESSORS ────────────────────────────────────────────────────────

    public function getStatusBadgeColorAttribute(): string
    {
        if ($this->rental_status === self::STATUS_RETURNED && !$this->isCompleted()) {
            return $this->hasOutstandingFine() ? 'red' : 'yellow';
        }

        return match($this->rental_status) {
            'waiting'   => 'yellow',
            'active'    => 'blue',
            'overdue'   => 'red',
            'returned'  => 'green',
            'cancelled' => 'gray',
            default     => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->rental_status === self::STATUS_RETURNED) {
            if ($this->hasOutstandingFine()) {
                return 'Denda belum lunas';
            }

            return $this->isCompleted() ? 'Selesai' : 'Belum lunas';
        }

        return match($this->rental_status) {
            'waiting'   => 'Menunggu Pembayaran',
            'booked'    => 'Booked',
            'active'    => 'Disewa',
            'overdue'   => 'Telat',
            'returned'  => 'Dikembalikan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default     => 'Unknown',
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match($this->payment_status) {
            'unpaid'  => 'Belum Bayar',
            'partial' => 'Sebagian',
            'paid'    => 'Lunas',
            default   => 'Unknown',
        };
    }

    public function isOverdue(): bool
    {
        return $this->rental_status === 'active'
            && $this->return_due_date->isPast();
    }

    public function getOverdueDaysAttribute(): int
    {
        // Jika sudah dikembalikan, ambil dari kolom overdue_days yang tersimpan
        if ($this->rental_status === 'returned') {
            return (int) ($this->attributes['overdue_days'] ?? 0);
        }
        if ($this->isOverdue()) {
            return $this->return_due_date->diffInDays(now());
        }
        return 0;
    }

    public function getTotalAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function getTotalRemainingAmountAttribute(): float
    {
        return max(0, $this->total_amount + $this->fine_amount - $this->paid_amount - $this->fine_paid_amount);
    }

    public function hasOutstandingFine(): bool
    {
        return in_array($this->fine_status, [self::FINE_UNPAID, self::FINE_PARTIAL], true)
            || (float) $this->fine_amount > (float) $this->fine_paid_amount;
    }

    public function isCompleted(): bool
    {
        return $this->rental_status === self::STATUS_RETURNED
            && $this->payment_status === self::PAYMENT_PAID
            && in_array($this->fine_status, [self::FINE_NONE, self::FINE_PAID], true)
            && !$this->hasOutstandingFine();
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query
            ->where('rental_status', self::STATUS_RETURNED)
            ->where('payment_status', self::PAYMENT_PAID)
            ->whereIn('fine_status', [self::FINE_NONE, self::FINE_PAID])
            ->whereRaw('COALESCE(fine_amount, 0) <= COALESCE(fine_paid_amount, 0)');
    }

    public static function statusLabels(): array
    {
        return [
            'waiting'   => 'Menunggu Pembayaran',
            'booked'    => 'Booked',
            'active'    => 'Disewa',
            'overdue'   => 'Telat',
            'returned'  => 'Dikembalikan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];
    }
}