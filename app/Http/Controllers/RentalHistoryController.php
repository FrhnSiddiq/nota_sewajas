<?php

namespace App\Http\Controllers;

use App\Models\RentalHistory;
use App\Models\Rental;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class RentalHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = RentalHistory::with(['branch', 'customer', 'createdBy', 'returnedBy'])
            ->where('payment_status', Rental::PAYMENT_PAID)
            ->whereIn('fine_status', [Rental::FINE_NONE, Rental::FINE_PAID])
            ->when($user->isSales(), function ($q) use ($user) {
                $q->whereIn('branch_id', $user->accessible_branch_ids);
            })
            ->when($user->isAdminToko(), function ($q) use ($user) {
                $q->whereIn('branch_id', $user->accessible_branch_ids);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q2) use ($search) {
                    $q2->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('branch_id') && $user->isSuperAdmin(), function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('completed_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('completed_at', '<=', $request->date_to);
            })
            ->latest('completed_at');

        $histories = $query->paginate(15)->withQueryString();
        $branches = $user->isSuperAdmin() ? Branch::orderBy('name')->get() : collect();

        return view('rental_histories.index', compact('histories', 'branches'));
    }

    public function show(RentalHistory $rentalHistory)
    {
        $this->authorize('view', $rentalHistory);
        
        $rentalHistory->load(['branch', 'customer', 'createdBy', 'returnedBy']);
        
        $history = $rentalHistory;
        return view('rental_histories.show', compact('history'));
    }

    public function downloadInvoice(RentalHistory $rentalHistory)
    {
        $this->authorize('view', $rentalHistory);

        $rentalHistory->load(['branch', 'customer', 'createdBy', 'returnedBy']);
        $pdf = Pdf::loadView('rental_histories.invoice', ['history' => $rentalHistory])
            ->setPaper('a4', 'portrait');

        return $pdf->download($rentalHistory->invoice_number . '-invoice.pdf');
    }
}