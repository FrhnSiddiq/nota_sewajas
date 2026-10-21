<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ActivityLogFileService;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function data(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['super_admin', 'admin_toko', 'sales'], true), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $allowedBranchIds = $this->dashboardBranchIds($user);
        if ($allowedBranchIds !== null) {
            abort_if($allowedBranchIds === [], 403, 'Akun Anda belum memiliki cabang yang ditetapkan.');
            abort_if(
                isset($validated['branch_id']) && ! in_array((int) $validated['branch_id'], $allowedBranchIds, true),
                403,
                'Cabang di luar cakupan akses.'
            );
        }

        $selectedBranchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        if ($user->isAdminToko() && $selectedBranchId === null) {
            $primaryBranchId = (int) ($user->branch_id ?? 0);
            $selectedBranchId = in_array($primaryBranchId, $allowedBranchIds, true)
                ? $primaryBranchId
                : $allowedBranchIds[0];
        }
        $branchIds = $allowedBranchIds;
        if ($selectedBranchId !== null) {
            $branchIds = [$selectedBranchId];
        }

        $salesUserId = $user->isSales() ? $user->id : ($validated['sales_user_id'] ?? null);
        if ($salesUserId !== null && ! $user->isSales()) {
            $salesQuery = User::query()->whereKey($salesUserId)->where('role', 'sales');
            if ($branchIds !== null) {
                $salesQuery->whereIn('branch_id', $branchIds);
            }
            abort_unless($salesQuery->exists(), 403, 'Sales di luar cabang yang dipilih.');
        }

        $filters = [
            'scope_type' => $user->role,
            'branch_ids' => $branchIds,
            'sales_user_id' => $salesUserId,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];

        $payload = $this->dashboardService->buildPayload($filters);
        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE);
    }


    public function index()
    {
        $user = Auth::user();

        return match ($user->role) {
            'super_admin' => $this->superAdminDashboard($user),
            'admin_toko'  => $this->adminDashboard($user),
            'sales'       => $this->salesDashboard($user),
            default       => abort(403),
        };
    }


    private function superAdminDashboard($user)
    {
        $branches = \App\Models\Branch::all();
        $sales = \App\Models\User::where('role', 'sales')->get();
        $payload = $this->dashboardService->getDashboardDataForUser($user);
        $stats = $payload['stats'] ?? [];
        $selectedBranchId = null;
        return view('dashboard.super-admin', compact('stats', 'branches', 'sales', 'selectedBranchId'));
    }

    private function adminDashboard($user)
    {
        $branchIds = $this->dashboardBranchIds($user) ?? [];
        abort_if($branchIds === [], 403, 'Akun Admin Toko belum memiliki cabang yang ditetapkan.');
        $branches = Branch::whereIn('id', $branchIds)->orderBy('name')->get();
        $sales = User::where('role', 'sales')->whereIn('branch_id', $branchIds)->orderBy('name')->get();
        $payload = $this->dashboardService->buildPayload([
            'scope_type' => 'admin',
            'branch_ids' => $branchIds,
            'sales_user_id' => null,
        ]);
        $stats = $payload['stats'] ?? [];
        $primaryBranchId = (int) ($user->branch_id ?? 0);
        $selectedBranchId = in_array($primaryBranchId, $branchIds, true) ? $primaryBranchId : $branchIds[0];
        return view('dashboard.admin', compact('stats', 'branches', 'sales', 'selectedBranchId'));
    }

    private function salesDashboard($user)
    {
        $branches = \App\Models\Branch::whereIn('id', $user->accessible_branch_ids)->get();
        $sales = \App\Models\User::where('id', $user->id)->get();
        $payload = $this->dashboardService->getDashboardDataForUser($user);
        $stats = $payload['stats'] ?? [];
        return view('dashboard.sales', compact('stats', 'branches', 'sales'));
    }

    private function dashboardBranchIds(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        if ($user->isAdminToko()) {
            return $user->accessible_branch_ids;
        }

        $branchIds = $user->accessible_branch_ids;
        return $branchIds !== [] ? $branchIds : ($user->branch_id ? [(int) $user->branch_id] : []);
    }

    public function downloadActivityLog()
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $service = new ActivityLogFileService();
        $service->backfill();
        $path = $service->getLogPath();

        if (!file_exists($path)) {
            return back()->with('error', 'File log belum tersedia.');
        }

        $filename = 'activity_log_' . now()->format('Y-m-d_H-i-s') . '.log';

        return Response::download($path, $filename, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

}

