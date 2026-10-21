<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalItem extends Model
{
    protected $fillable = [
        'rental_id', 'product_id', 'product_name', 'product_size', 'product_color',
        'quantity', 'price_per_day', 'duration_days', 'subtotal',
        'return_condition', 'damage_fee', 'return_notes', 'is_returned', 'returned_at',
    ];

    protected $casts = [
        'price_per_day' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'damage_fee' => 'decimal:2',
        'is_returned' => 'boolean',
        'returned_at' => 'datetime',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
