<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSize extends Model
{
    use HasFactory;

    protected $table = 'product_sizes';

    protected $fillable = [
        'product_id',
        'size',
        'stock_total',
        'stock_available',
        'stock_maintenance',
        'stock_lost',
    ];

    protected $casts = [
        'stock_total' => 'integer',
        'stock_available' => 'integer',
        'stock_maintenance' => 'integer',
        'stock_lost' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
