<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Foundation\Http\FormRequest;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'           => 'required|exists:customers,id',
            'rental_date'           => 'required|date',
            'duration_days'         => 'required|integer|min:1',
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.product_size'  => 'required|in:XS,S,M,L,XL,XXL,3XL,4XL,5XL,6XL,7XL',
            'items.*.quantity'      => 'required|integer|min:1',
            'discount'              => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string',
            'payment_method'        => 'required|string|in:cash,qris,transfer',
            'guarantee_type'        => 'required|string|in:ktp,sim,deposit,custom',
            'guarantee_deposit'     => 'nullable|numeric|min:0',
            'guarantee_notes'       => 'nullable|string|max:500',
            'guarantee_id_photo'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Nested stock checks require successfully validated item arrays.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $this->input('items', []);

            $aggregated = [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $size = (string) ($item['product_size'] ?? '');
                $quantity = (int) ($item['quantity'] ?? 0);
                if ($productId <= 0 || $quantity <= 0 || $size === '') {
                    continue;
                }
                $key = $productId . '|' . $size;
                $aggregated[$key] = ($aggregated[$key] ?? 0) + $quantity;
            }

            if (empty($aggregated)) {
                return;
            }

            $productIds = array_unique(array_map(fn($k) => (int) explode('|', $k)[0], array_keys($aggregated)));
            $sizesRequested = array_unique(array_map(fn($k) => explode('|', $k)[1], array_keys($aggregated)));
            $productSizes = ProductSize::whereIn('product_id', $productIds)
                ->whereIn('size', $sizesRequested)
                ->get();
            $products = Product::withCount('sizes')->whereIn('id', $productIds)->get();

            foreach ($aggregated as $key => $requested) {
                [$productId, $size] = explode('|', $key);
                $productSize = $productSizes
                    ->where('product_id', (int) $productId)
                    ->where('size', $size)
                    ->first();
                $productName = $products->firstWhere('id', (int) $productId)->name ?? 'Produk';

                if (!$productSize) {
                    $product = $products->firstWhere('id', (int) $productId);
                    if (!$product || $product->sizes_count > 0 || $product->size !== $size) {
                        $validator->errors()->add('items', "Ukuran {$size} tidak tersedia untuk produk {$productName}.");
                        continue;
                    }
                    if ($product && $requested > (int) $product->stock_available) {
                        $validator->errors()->add(
                            'items',
                            "Stok produk {$product->name} tidak mencukupi (tersedia: {$product->stock_available}, diminta: {$requested})."
                        );
                    }
                    continue;
                }

                if ($requested > (int) $productSize->stock_available) {
                    $validator->errors()->add(
                        'items',
                        "Stok ukuran {$size} produk {$productName} tidak mencukupi (tersedia: {$productSize->stock_available}, diminta: {$requested})."
                    );
                }
            }

            $guaranteeType = $this->input('guarantee_type');

            if ($guaranteeType === 'deposit') {
                $deposit = $this->input('guarantee_deposit');
                if (!$deposit || (float) $deposit <= 0) {
                    $validator->errors()->add('guarantee_deposit', 'Jumlah deposit wajib diisi untuk jenis jaminan Deposit Uang.');
                }
            }

            if ($guaranteeType === 'ktp' || $guaranteeType === 'sim') {
                if (!$this->hasFile('guarantee_id_photo')) {
                    $validator->errors()->add('guarantee_id_photo', 'Foto identitas wajib diupload untuk jenis jaminan ' . ($guaranteeType === 'ktp' ? 'KTP' : 'SIM') . '.');
                }
            }

            if ($guaranteeType === 'custom') {
                $notes = trim((string) ($this->input('guarantee_notes') ?? ''));
                if ($notes === '') {
                    $validator->errors()->add('guarantee_notes', 'Deskripsi jaminan custom wajib diisi.');
                }
            }
        });
    }
}
