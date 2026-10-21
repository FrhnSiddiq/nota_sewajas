<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $phoneQuery = preg_replace('/\D+/', '', $query);
        $user = $request->user();

        $customers = collect();
        $rentals = collect();
        $products = collect();

        if ($query !== '') {
            $customers = Customer::withTrashed() // Include soft-deleted
                ->when($user->isSales(), fn (Builder $q) => $q->where('user_id', $user->id))
                ->when(!$user->isSuperAdmin() && !$user->isSales(), fn (Builder $q) => $q->whereIn('branch_id', $user->accessible_branch_ids))
                ->where(function (Builder $q) use ($query, $phoneQuery) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");

                    if ($phoneQuery !== '') {
                        $q->orWhere('phone', 'like', "%{$phoneQuery}%");
                    }
                })
                ->withCount('rentals')
                ->latest()
                ->limit(8)
                ->get();

            $rentals = Rental::query()
                ->with(['customer', 'branch'])
                ->when(!$user->isSuperAdmin(), fn (Builder $q) => $q->whereIn('branch_id', $user->accessible_branch_ids))
                ->where(function (Builder $q) use ($query, $phoneQuery) {
                    $q->where('invoice_number', 'like', "%{$query}%")
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($query, $phoneQuery) {
                            $customerQuery->where('name', 'like', "%{$query}%")
                                ->orWhere('phone', 'like', "%{$query}%");

                            if ($phoneQuery !== '') {
                                $customerQuery->orWhere('phone', 'like', "%{$phoneQuery}%");
                            }
                        });
                })
                ->latest()
                ->limit(8)
                ->get();

            $products = Product::query()
                ->when(!$user->isSuperAdmin(), fn (Builder $q) => $q->whereIn('branch_id', $user->accessible_branch_ids))
                ->where(function (Builder $q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('color', 'like', "%{$query}%");
                })
                ->latest()
                ->limit(8)
                ->get();
        }

        return view('search.index', [
            'query' => $query,
            'customers' => $customers,
            'rentals' => $rentals,
            'products' => $products,
        ]);
    }
}
