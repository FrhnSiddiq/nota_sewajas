<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PointsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $salesQuery = User::where('role', 'sales')
            ->with('branch');

        if ($user->isSales()) {
            $salesQuery->where('id', $user->id);
        }

        $allowedBranchIds = $user->isSuperAdmin() ? null : $user->accessible_branch_ids;
        if ($allowedBranchIds !== null) {
            $salesQuery->whereIn('branch_id', $allowedBranchIds);
        }

        if ($request->filled('branch_id')) {
            $validated = $request->validate([
                'branch_id' => ['integer', 'exists:branches,id'],
            ]);
            $branchId = (int) $validated['branch_id'];
            abort_unless($allowedBranchIds === null || in_array($branchId, $allowedBranchIds, true), 403, 'Cabang di luar cakupan akses.');
            $salesQuery->where('branch_id', $branchId);
        }

        $salesList = $salesQuery->orderByDesc('total_points')->paginate(20);

        $totalPoints = (clone $salesQuery)->sum('total_points');

        $branches = $allowedBranchIds === null
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $allowedBranchIds)->orderBy('name')->get();

        return view('points.index', compact('salesList', 'totalPoints', 'branches'));
    }
}
