<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductStockMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = ProductStockMaintenance::with(['product.category', 'productSize', 'rental.customer'])
            ->where('status', 'maintenance')
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->whereHas('product.branches', fn($bq) => $bq->whereIn('branches.id', $user->accessible_branch_ids));
            })
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($qq) use ($s) {
                    $qq->whereHas('product', fn($pq) => $pq->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
                        ->orWhere('size', 'like', "%{$s}%");
                });
            })
            ->latest();

        $items = $query->paginate(20)->withQueryString();

        return view('products.maintenance', compact('items'));
    }

    public function restore(Request $request, ProductStockMaintenance $maintenance)
    {
        $this->ensureBranchAccess($maintenance->product);

        DB::transaction(function () use ($maintenance) {
            // Kunci baris record: cek status dilakukan di dalam transaksi supaya
            // submit ganda (double klik / dua tab) tidak mengembalikan stok dua kali.
            $locked = ProductStockMaintenance::whereKey($maintenance->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'maintenance', 422, 'Item tidak dalam status maintenance.');

            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            $ps = ProductSize::whereKey($locked->product_size_id)->lockForUpdate()->firstOrFail();

            if ($locked->condition === 'hilang') {
                // Barang hilang: total stok sudah berkurang saat pengembalian,
                // jadi restore berarti "barang ditemukan kembali".
                $product->restoreLostStock($ps, $locked->quantity);
            } else {
                $product->restoreMaintenanceStock($ps, $locked->quantity);
            }

            $locked->update([
                'status' => 'restored',
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);
        });

        return back()->with('success', 'Stok berhasil dikembalikan (restore).');
    }

    public function dispose(Request $request, ProductStockMaintenance $maintenance)
    {
        $this->ensureBranchAccess($maintenance->product);

        DB::transaction(function () use ($maintenance) {
            $locked = ProductStockMaintenance::whereKey($maintenance->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'maintenance', 422, 'Item tidak dalam status maintenance.');

            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            $ps = ProductSize::whereKey($locked->product_size_id)->lockForUpdate()->firstOrFail();

            if ($locked->condition === 'hilang') {
                // Barang hilang: total stok sudah berkurang saat pengembalian.
                // Mengurangi total lagi di sini akan menghapus stok unit yang baik.
                $product->disposeLostStock($ps, $locked->quantity);
            } else {
                $product->disposeMaintenanceStock($ps, $locked->quantity);
            }

            $locked->update([
                'status' => 'disposed',
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);
        });

        return back()->with('success', 'Item maintenance dihapus (stok total berkurang).');
    }

    private function ensureBranchAccess(Product $product): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return;
        $allowed = $user->accessible_branch_ids;
        $pids = $product->branches()->pluck('branches.id')->map(fn($id) => (int)$id)->all();
        if ($pids === [] && $product->branch_id) $pids = [(int)$product->branch_id];
        abort_unless(array_intersect($allowed, $pids) !== [], 403);
    }
}
