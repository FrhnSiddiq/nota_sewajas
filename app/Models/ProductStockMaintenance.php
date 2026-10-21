<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockMaintenance extends Model
{
    protected $fillable = [
        'product_id', 'product_size_id', 'rental_id', 'rental_item_id',
        'size', 'condition', 'quantity', 'damage_fee', 'status', 'notes',
        'created_by', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'damage_fee'  => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    // Produk bisa di-soft-delete selama record maintenance masih terbuka,
    // jadi relasi tetap harus terlihat agar record tidak macet (404/500).
    public function product(): BelongsTo { return $this->belongsTo(Product::class)->withTrashed(); }
    public function productSize(): BelongsTo { return $this->belongsTo(ProductSize::class); }
    public function rental(): BelongsTo { return $this->belongsTo(Rental::class); }
    public function rentalItem(): BelongsTo { return $this->belongsTo(RentalItem::class); }
}
