<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'category_id', 'code', 'name', 'description',
        'size', 'color', 'brand', 'rental_price', 'deposit_price',
        'stock_total', 'stock_available', 'photo', 'qr_code',
        'condition', 'status', 'notes', 'points',
    ];

    protected $casts = [
        'rental_price' => 'decimal:2',
        'deposit_price' => 'decimal:2',
        'points' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'product_branch');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function getTotalStockAttribute(): int
    {
        if ($this->relationLoaded('sizes')) {
            $sum = $this->sizes->sum('stock_total');
            if ($sum > 0 || $this->sizes->isNotEmpty()) return $sum;
        }

        $sum = (int) $this->sizes()->sum('stock_total');
        if ($sum > 0) return $sum;

        // Check if there are any size records (even with 0 stock)
        if ($this->sizes()->exists()) return 0;

        // Fallback ke kolom legacy untuk produk lama yang belum memiliki record di product_sizes
        return (int) ($this->attributes['stock_total'] ?? 0);
    }

    public function getAvailableStockAttribute(): int
    {
        if ($this->relationLoaded('sizes')) {
            $sum = $this->sizes->sum('stock_available');
            if ($sum > 0 || $this->sizes->isNotEmpty()) return $sum;
        }

        $sum = (int) $this->sizes()->sum('stock_available');
        if ($sum > 0) return $sum;

        // Check if there are any size records (even with 0 stock)
        if ($this->sizes()->exists()) return 0;

        // Fallback ke kolom legacy untuk produk lama yang belum memiliki record di product_sizes
        return (int) ($this->attributes['stock_available'] ?? 0);
    }

    public function getSizesDataAttribute(): array
    {
        return $this->sizes->mapWithKeys(function ($size) {
            return [$size->size => [
                'stock_total' => $size->stock_total,
                'stock_available' => $size->stock_available,
            ]];
        })->toArray();
    }

    public function decrementSizeStock(string $size, int $quantity): int
    {
        $productSize = $this->sizes()->where('size', $size)->lockForUpdate()->first();
        if ($productSize) {
            if ((int) $productSize->stock_available < $quantity) {
                return -1;
            }
            $productSize->decrement('stock_available', $quantity);
        } else {
            $newStock = (int) $this->stock_available - $quantity;
            if ($newStock < 0) {
                return -1;
            }
            $this->stock_available = $newStock;
        }
        $this->recomputeAggregateStock(outOfStock: true);
        return $this->available_stock;
    }

    public function incrementSizeStock(string $size, int $quantity): void
    {
        $productSize = $this->sizes()->where('size', $size)->lockForUpdate()->first();
        if ($productSize) {
            $productSize->increment('stock_available', $quantity);
        } else {
            $this->stock_available = (int) $this->stock_available + $quantity;
        }
        $this->recomputeAggregateStock(outOfStock: false);
    }

    public function incrementMaintenanceStock(string $size, int $quantity, string $condition = 'rusak_ringan'): void
    {
        $productSize = $this->sizes()->where('size', $size)->lockForUpdate()->first();
        if ($productSize) {
            if ($condition === 'hilang') {
                $productSize->increment('stock_lost', $quantity);
                // hilang: reduce total so it never returns to available
                $productSize->decrement('stock_total', $quantity);
                if ((int) $productSize->stock_total < 0) {
                    $productSize->update(['stock_total' => 0]);
                }
                // available was already decremented at rental creation, no increment
            } else {
                $productSize->increment('stock_maintenance', $quantity);
            }
        }
        $this->recomputeAggregateStock(outOfStock: false);
    }

    public function restoreMaintenanceStock(ProductSize $productSize, int $quantity): void
    {
        if ((int) $productSize->product_id !== (int) $this->id) {
            throw new \InvalidArgumentException('The size stock does not belong to this product.');
        }
        $available = max(0, (int) $productSize->stock_maintenance - $quantity);
        $productSize->stock_maintenance = $available;
        $productSize->increment('stock_available', $quantity);
        if ((int) $productSize->stock_available > (int) $productSize->stock_total) {
            $productSize->stock_available = (int) $productSize->stock_total;
        }
        $productSize->save();
        $this->recomputeAggregateStock(outOfStock: false);
    }

    public function disposeMaintenanceStock(ProductSize $productSize, int $quantity): void
    {
        if ((int) $productSize->product_id !== (int) $this->id) {
            throw new \InvalidArgumentException('The size stock does not belong to this product.');
        }
        $remaining = max(0, (int) $productSize->stock_maintenance - $quantity);
        $productSize->stock_maintenance = $remaining;
        // disposed: reduce total, never returns to available
        $newTotal = max(0, (int) $productSize->stock_total - $quantity);
        $productSize->stock_total = $newTotal;
        if ((int) $productSize->stock_available > $newTotal) {
            $productSize->stock_available = $newTotal;
        }
        $productSize->save();
        $this->recomputeAggregateStock(outOfStock: false);
    }

    /**
     * Barang hilang yang kemudian ditemukan kembali.
     * Saat pengembalian total stok sudah berkurang, jadi unitnya dikembalikan
     * ke total sekaligus ke tersedia, dan penanda hilang dihapus.
     */
    public function restoreLostStock(ProductSize $productSize, int $quantity): void
    {
        if ((int) $productSize->product_id !== (int) $this->id) {
            throw new \InvalidArgumentException('The size stock does not belong to this product.');
        }

        $productSize->stock_lost = max(0, (int) $productSize->stock_lost - $quantity);
        $productSize->stock_total = (int) $productSize->stock_total + $quantity;
        $available = (int) $productSize->stock_available + $quantity;
        $productSize->stock_available = min($available, (int) $productSize->stock_total);
        $productSize->save();

        $this->recomputeAggregateStock(outOfStock: false);
    }

    /**
     * Barang hilang yang dinyatakan hilang permanen.
     * Total stok sudah berkurang sejak pengembalian, sehingga di sini hanya
     * penanda "hilang tertutup" yang dikurangi (total/tersedia tidak disentuh).
     */
    public function disposeLostStock(ProductSize $productSize, int $quantity): void
    {
        if ((int) $productSize->product_id !== (int) $this->id) {
            throw new \InvalidArgumentException('The size stock does not belong to this product.');
        }

        $productSize->stock_lost = max(0, (int) $productSize->stock_lost - $quantity);
        $productSize->save();

        $this->recomputeAggregateStock(outOfStock: false);
    }

    public function setSizeStockAvailable(ProductSize $productSize, int $stockAvailable): void
    {
        if ((int) $productSize->product_id !== (int) $this->id) {
            throw new \InvalidArgumentException('The size stock does not belong to this product.');
        }

        $stockDecreased = $stockAvailable < (int) $productSize->stock_available;
        $productSize->stock_available = $stockAvailable;
        $productSize->save();

        $this->recomputeAggregateStock(outOfStock: $stockDecreased);
    }

    protected function recomputeAggregateStock(bool $outOfStock = true): void
    {
        $this->load('sizes');
        $this->stock_available = $this->available_stock;
        $this->stock_total = $this->total_stock;
        if ($outOfStock) {
            $this->status = ($this->available_stock <= 0) ? 'rented' : $this->status;
        } else {
            $this->status = ($this->status === 'rented' && $this->available_stock > 0) ? 'available' : $this->status;
        }
        $this->save();
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            // Jika URL external (http:// atau https://)
            if (filter_var($this->photo, FILTER_VALIDATE_URL)) {
                return $this->photo;
            }
            return asset('storage/' . $this->photo);
        }
        // Default no product image
        return 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=400&h=500&fit=crop';
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        if ($this->qr_code) {
            return asset('storage/' . $this->qr_code);
        }
        return null;
    }

    public function isAvailable(): bool
    {
        return $this->stock_available > 0 && $this->status === 'available';
    }

    public function getRentalPriceFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->rental_price, 0, ',', '.');
    }
}
