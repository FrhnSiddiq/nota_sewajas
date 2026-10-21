<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $products = DB::table('products')
            ->whereNotNull('size')
            ->where('size', '!=', '')
            ->get(['id', 'size', 'stock_total', 'stock_available']);

        foreach ($products as $product) {
            $exists = DB::table('product_sizes')
                ->where('product_id', $product->id)
                ->where('size', $product->size)
                ->exists();

            if ($exists) {
                continue;
            }

            $stockTotal = $product->stock_total ?? 0;
            $stockAvailable = $product->stock_available ?? 0;

            DB::table('product_sizes')->insert([
                'product_id' => $product->id,
                'size' => $product->size,
                'stock_total' => $stockTotal,
                'stock_available' => $stockAvailable,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data migration is not easily reversible for individual products;
        // only truncate if needed. Keep it safe by not removing anything.
    }
};
