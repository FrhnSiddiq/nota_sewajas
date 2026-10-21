<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Category;
use App\Models\Branch;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class ProductController extends Controller
{
  public function index(Request $request)
  {
      $user = Auth::user();
        $query = Product::with(['category', 'branches', 'sizes'])
            ->when(!$user->isSuperAdmin(), function($q) use ($user) {
                $q->whereHas('branches', fn($bq) => $bq->whereIn('branches.id', $user->accessible_branch_ids));
            })
            ->when($request->category, fn($q) => $q->where('category_id', $request->category))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->size, fn($q) => $q->whereHas('sizes', fn($sq) => $sq->where('size', $request->size)))
          ->when($request->branch_id, fn($q) => $q->whereHas('branches', fn($bq) => $bq->where('branches.id', $request->branch_id)))
          ->when(
              $request->search,
              fn($q) => $q->where(function ($q2) use ($request) {
                  $q2->where('name', 'like', "%{$request->search}%")
                      ->orWhere('code', 'like', "%{$request->search}%")
                      ->orWhere('color', 'like', "%{$request->search}%");
              }),
          )
          ->latest();

       $products   = $query->paginate(16)->withQueryString();
       $categories = Category::where('is_active', true)->get();
       $branches = Branch::where('is_active', true)->get();
       $availableSizes = ProductSize::select('size')->distinct()->orderByRaw("FIELD(size,'XS','S','M','L','XL','XXL','3XL','4XL','5XL','6XL','7XL')")->pluck('size')->toArray();
      $stats = [
         'total' => (clone $query)->count(),
         'available' => (clone $query)->where('status', 'available')->count(),
         'maintenance' => (clone $query)->where('status', 'maintenance')->count(),
         'rented_units' => (clone $query)->where('status', 'rented')->sum(\DB::raw('stock_total - stock_available')),
     ];

       return view('products.index', compact('products', 'categories', 'branches', 'stats', 'availableSizes'));
 }

    public function create()
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->branch_id) {
            abort(403, 'Akun Admin Toko Anda belum memiliki cabang yang ditetapkan. Silakan hubungi Super Admin untuk menetapkan cabang terlebih dahulu.');
        }
        $categories = Category::where('is_active', true)->get();
        $branches = Branch::where('is_active', true)->get();
        $nextCode = $this->generateCode();
        return view('products.create', compact('categories', 'branches', 'nextCode'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'   => 'required|exists:categories,id',
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string',
            'color'         => 'nullable|string|max:50',
            'brand'         => 'nullable|string|max:100',
            'rental_price'  => 'required|numeric|min:0',
            'deposit_price' => 'nullable|numeric|min:0',
            'points'        => 'required|integer|min:0|max:65535',
            'condition'     => 'required|in:excellent,good,fair,poor',
            'status'        => 'required|in:available,maintenance,inactive',
            'notes'         => 'nullable|string',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'branch_ids'    => 'nullable|array',
            'branch_ids.*'  => 'exists:branches,id',
            'sizes'         => 'required|array|min:1',
            'sizes.*.stock_total'    => 'required|integer|min:0',
            'sizes.*.stock_available' => 'required|integer|min:0',
        ]);

        foreach ($data['sizes'] as $sizeName => $stock) {
            if ($stock['stock_available'] > $stock['stock_total']) {
                return back()->withErrors([
                    "sizes.{$sizeName}.stock_available" => "Stok tersedia tidak boleh melebihi stok total untuk ukuran {$sizeName}.",
                ])->withInput();
            }
        }

        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $branchIds = $request->filled('branch_ids') ? $request->input('branch_ids') : [$user->branch_id ?? 1];
            $legacyBranchId = $branchIds[0];
        } else {
            // Admin Toko must have a branch assigned
            if (!$user->branch_id) {
                return back()->with('error', 'Akun Admin Toko Anda belum memiliki cabang yang ditetapkan. Silakan hubungi Super Admin untuk menetapkan cabang terlebih dahulu.');
            }
            $branchIds = [$user->branch_id];
            $legacyBranchId = $user->branch_id;
        }

        $data['branch_id']       = $legacyBranchId;
        $data['size']            = null;
        $data['stock_total']     = array_sum(array_column($data['sizes'], 'stock_total'));
        $data['stock_available'] = array_sum(array_column($data['sizes'], 'stock_available'));
        $sizesData               = $this->formatSizesData($data);

        if ($request->hasFile('image')) {
            $data['photo'] = $request->file('image')->store('products', 'public');
        }

        unset($data['image'], $data['branch_ids'], $data['sizes']);

        // Retry loop to handle race conditions on product code uniqueness
        $product = null;
        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $data['code'] = $this->generateCode();

            try {
                $product = DB::transaction(function () use ($data, $branchIds, $sizesData) {
                    $product = Product::create($data);
                    $product->branches()->sync($branchIds);
                    $product->sizes()->createMany($sizesData);
                    return $product;
                });
                break; // Success, exit retry loop
            } catch (QueryException $e) {
                // Check if it's a duplicate key violation on product code (SQLSTATE 23000)
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'products_code_unique')) {
                    if ($attempt === $maxAttempts) {
                        throw ValidationException::withMessages([
                            'code' => 'Gagal membuat kode produk unik setelah beberapa percobaan. Silakan coba lagi.',
                        ]);
                    }
                    // Continue to next iteration to generate a new code
                    continue;
                }
                throw $e; // Re-throw if it's a different error
            }
        }

        $this->generateQrCode($product);

        return redirect()->route('products.show', $product)
            ->with('success', 'Produk berhasil ditambahkan!');
    }

    public function update(Request $request, Product $product)
    {
        $user = Auth::user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko())) {
            abort(403, 'Unauthorized action.');
        }
          $this->ensureBranchAccess($product);

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:50',
            'brand' => 'nullable|string|max:100',
            'rental_price' => 'required|numeric|min:0',
            'deposit_price' => 'nullable|numeric|min:0',
            'points' => 'required|integer|min:0|max:65535',
            'condition' => 'required|in:excellent,good,fair,poor',
            'status' => 'sometimes|in:available,maintenance,inactive',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'sizes' => 'sometimes|array|min:1',
            'sizes.*.stock_total'    => 'required|integer|min:0',
            'sizes.*.stock_available' => 'required|integer|min:0',
        ]);

        $requestedBranchIds = null;
        if ($request->filled('branch_ids')) {
            $requestedBranchIds = array_map('intval', $data['branch_ids']);

            if (!$user->isSuperAdmin()) {
                $allowedBranchIds = $user->accessible_branch_ids;
                $existingBranchIds = $product->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
                if ($existingBranchIds === [] && $product->branch_id) {
                    $existingBranchIds = [(int) $product->branch_id];
                }

                abort_unless(array_diff($requestedBranchIds, array_merge($allowedBranchIds, $existingBranchIds)) === [], 403);
                $requestedBranchIds = array_values(array_unique(array_merge(
                    array_diff($existingBranchIds, $allowedBranchIds),
                    array_intersect($requestedBranchIds, $allowedBranchIds)
                )));
            }
        }

        if ($request->hasFile('image')) {
            if ($product->photo && Storage::disk('public')->exists($product->photo)) {
                Storage::disk('public')->delete($product->photo);
            }
            $data['photo'] = $request->file('image')->store('products', 'public');
        } elseif ($request->input('remove_photo') === '1') {
            if ($product->photo && Storage::disk('public')->exists($product->photo)) {
                Storage::disk('public')->delete($product->photo);
            }
            $data['photo'] = null;
        } else {
            $data['photo'] = $product->photo;
        }

        unset($data['image'], $data['branch_ids']);

        if (array_key_exists('sizes', $data)) {
            $sizesData = $this->formatSizesData($data);

            DB::transaction(function () use ($product, &$data, &$sizesData) {
                $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
                $existingSizes = $lockedProduct->sizes()->lockForUpdate()->get()->keyBy('size');
                $submittedSizeNames = array_column($sizesData, 'size');

                foreach ($existingSizes as $sizeName => $existingSize) {
                    $rentedCount = max(0, (int) $existingSize->stock_total - (int) $existingSize->stock_available);
                    if ($rentedCount > 0 && !in_array($sizeName, $submittedSizeNames, true)) {
                        throw ValidationException::withMessages([
                            'sizes' => "Ukuran {$sizeName} tidak dapat dihapus karena masih ada {$rentedCount} unit yang sedang disewa.",
                        ]);
                    }
                }

                foreach ($sizesData as &$sizeData) {
                    $existingSize = $existingSizes->get($sizeData['size']);
                    $rentedCount = $existingSize
                        ? max(0, (int) $existingSize->stock_total - (int) $existingSize->stock_available)
                        : 0;
                    $newTotal = (int) $sizeData['stock_total'];

                    if ($newTotal < $rentedCount) {
                        throw ValidationException::withMessages([
                            "sizes.{$sizeData['size']}.stock_total" => "Stok total ukuran {$sizeData['size']} tidak boleh kurang dari {$rentedCount} unit yang sedang disewa.",
                        ]);
                    }

                    $sizeData['stock_available'] = $newTotal - $rentedCount;
                }
                unset($sizeData);

                $data['stock_total'] = array_sum(array_column($sizesData, 'stock_total'));
                $data['stock_available'] = array_sum(array_column($sizesData, 'stock_available'));
                $data['size'] = null;
                unset($data['sizes']);

                $allocatedStock = array_sum(array_map(
                    fn ($sizeData) => (int) $sizeData['stock_total'] - (int) $sizeData['stock_available'],
                    $sizesData
                ));
                $effectiveStatus = $data['status'] ?? $lockedProduct->status;
                if (in_array($effectiveStatus, ['available', 'rented'], true)) {
                    if ($data['stock_available'] > 0) {
                        $data['status'] = 'available';
                    } elseif ($allocatedStock > 0) {
                        $data['status'] = 'rented';
                    }
                }

                $lockedProduct->update($data);
                $lockedProduct->sizes()->delete();
                $lockedProduct->sizes()->createMany($sizesData);
            });
        } else {
            $product->update($data);
        }

        if ($requestedBranchIds !== null) {
            $product->branches()->sync($requestedBranchIds);
        }

        return redirect()->route('products.show', $product)->with('success', 'Produk berhasil diperbarui!');
    }

    public function show(Product $product)
    {
        $user = Auth::user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko() && !$user->isSales())) {
            abort(403, 'Unauthorized action.');
        }
        $this->ensureBranchAccess($product);

        $product->load(['sizes', 'category', 'branch', 'rentalItems' => fn($q) => $q->with('rental.customer')->latest()->limit(10)]);
        return view('products.show', compact('product'));
    }

    public function updateStock(Request $request, Product $product)
    {
        $user = Auth::user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko())) {
            abort(403, 'Unauthorized action.');
        }
        $this->ensureBranchAccess($product);
        $validated = $request->validate([
            'size_id' => [
                'required',
                'integer',
                Rule::exists('product_sizes', 'id')->where(fn($query) => $query->where('product_id', $product->id)),
            ],
            'stock_available' => ['required', 'integer', 'min:0'],
            'stock_note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($request, $product, $validated) {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $productSize = ProductSize::query()
                ->where('product_id', $lockedProduct->id)
                ->lockForUpdate()
                ->find($validated['size_id']);

            if (!$productSize) {
                throw ValidationException::withMessages([
                    'size_id' => 'Ukuran yang dipilih tidak tersedia untuk produk ini.',
                ]);
            }

            $newStock = (int) $validated['stock_available'];
            if ($newStock > (int) $productSize->stock_total) {
                throw ValidationException::withMessages([
                    'stock_available' => "Stok tersedia ukuran {$productSize->size} tidak boleh melebihi stok total ({$productSize->stock_total}).",
                ]);
            }

            $oldStock = (int) $productSize->stock_available;
            if ($newStock !== $oldStock) {
                $lockedProduct->setSizeStockAvailable($productSize, $newStock);
            }

            $note = $validated['stock_note'] ?? null;
            $oldValues = [
                'size_id' => (int) $productSize->id,
                'size' => $productSize->size,
                'stock_available' => $oldStock,
            ];
            $newValues = [
                'size_id' => (int) $productSize->id,
                'size' => $productSize->size,
                'stock_available' => $newStock,
                'stock_note' => $note,
            ];

            $description = 'Ukuran ' . $productSize->size . ': ' . \App\Services\ActivityLogFormatter::format(
                $oldValues,
                $newValues,
                Product::class,
                $lockedProduct->id,
                'update_product_stock'
            );

            ActivityLog::create([
                'user_id' => Auth::id(),
                'branch_id' => Auth::user()->branch_id,
                'action' => 'update_product_stock',
                'model_type' => Product::class,
                'model_id' => $lockedProduct->id,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return [
                'size' => $productSize->size,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
            ];
        });

        return back()->with(
            'success',
            "Stok ukuran {$result['size']} berhasil diperbarui dari {$result['old_stock']} menjadi {$result['new_stock']}!"
        );
    }

    public function edit(Product $product)
    {
        $user = Auth::user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko())) {
            abort(403, 'Unauthorized action.');
        }
        $this->ensureBranchAccess($product);
        $categories = Category::where('is_active', true)->get();
        $branches = Branch::where('is_active', true)->get();
        $selectedBranches = $product->branches->pluck('id')->toArray();
        $product->load('sizes');
        $initialSizes = old('sizes', $product->sizesData);
        $rentedBySize = $product->sizes->mapWithKeys(fn ($size) => [
            $size->size => max(0, (int) $size->stock_total - (int) $size->stock_available),
        ])->all();
        return view('products.edit', compact('product', 'categories', 'branches', 'selectedBranches', 'initialSizes', 'rentedBySize'));
    }

    public function regenerateQr(Product $product)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $this->generateQrCode($product);
        return back()->with('success', 'QR Code berhasil digenerate ulang!');
    }

    private function generateCode(): string
    {
        $maxSeq = Product::withTrashed()
            ->where(function ($query) {
                $query->where('code', 'like', 'PRD-%')
                    ->orWhere('code', 'like', 'PRD0%');
            })
            ->max('code');

        $seq = 1;
        if ($maxSeq) {
            $maxSeqNum = (int) preg_replace('/[^0-9]/', '', $maxSeq);
            $seq = $maxSeqNum + 1;
        }

        // Ensure the generated code doesn't already exist (including soft-deleted)
        // due to unique constraint on code column
        $code = 'PRD-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        while (Product::withTrashed()->where('code', $code)->exists()) {
            $seq++;
            $code = 'PRD-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    private function formatSizesData(array $data): array
    {
        $sizes = $data['sizes'] ?? [];

        return collect($sizes)->map(function ($stock, $sizeName) {
            return [
                'size' => $sizeName,
                'stock_total' => (int) $stock['stock_total'],
                'stock_available' => (int) $stock['stock_available'],
            ];
        })->values()->toArray();
    }

    public function destroy(Product $product)
    {
        $user = Auth::user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko())) {
            abort(403, 'Unauthorized action.');
        }
        $this->ensureBranchAccess($product);

        $oldValues = [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'status' => $product->status,
            'stock_available' => (int) $product->stock_available,
            'stock_total' => (int) $product->stock_total,
        ];

        $hasActiveRental = $product->rentalItems()
            ->whereHas('rental', function ($query) {
                $query->whereIn('rental_status', ['waiting', 'active', 'overdue']);
            })
            ->exists();

        if ($hasActiveRental) {
            return back()->with('error', 'Produk tidak dapat dihapus karena masih memiliki rental aktif (menunggu, disewa, atau terlambat). Silakan tunggu semua rental selesai dikembalikan.');
        }

        try {
            DB::transaction(function () use ($product, $oldValues) {
                $product->rentalItems()->update(['product_id' => null]);
                \App\Models\Chat::where('product_id', $product->id)->update(['product_id' => null]);
                \App\Models\Laundry::where('produk_id', $product->id)->update(['produk_id' => null]);

                $product->forceDelete();

                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'branch_id' => Auth::user()->branch_id,
                    'action' => 'delete_product',
                    'model_type' => Product::class,
                    'model_id' => $oldValues['id'],
                    'description' => \App\Services\ActivityLogFormatter::format($oldValues, null, Product::class, $oldValues['id'], 'delete_product'),
                    'old_values' => $oldValues,
                    'new_values' => null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $sqlState = $e->getCode();
            $isForeignKeyError = is_string($sqlState) && str_starts_with($sqlState, '23000');

            if ($isForeignKeyError) {
                return back()->with('error', 'Produk tidak dapat dihapus karena masih terhubung dengan data lain yang perlu dipertahankan.');
            }

            return back()->with('error', 'Produk tidak dapat dihapus karena masih terhubung dengan data transaksi lain di database.');
        }

        foreach ([$product->photo, $product->qr_code] as $file) {
            if ($file && Storage::disk('public')->exists($file)) {
                Storage::disk('public')->delete($file);
            }
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus permanen. Riwayat penyewaan tetap tersimpan.');
    }

    private function ensureBranchAccess(Product $product): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($user->isSuperAdmin()) {
            return;
        }

        $allowedBranchIds = $user->accessible_branch_ids;
        $productBranchIds = $product->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
        if ($productBranchIds === [] && $product->branch_id) {
            $productBranchIds = [(int) $product->branch_id];
        }

        abort_unless(array_intersect($allowedBranchIds, $productBranchIds) !== [], 403);
    }

    private function generateQrCode(Product $product): void
    {
        $qrData = route('products.show', $product);
        $path = 'qrcodes/products/' . $product->code . '.svg';
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $svg = QrCode::format('svg')->size(200)->generate($qrData);
        file_put_contents($fullPath, $svg);
        $product->update(['qr_code' => $path]);
    }
}
