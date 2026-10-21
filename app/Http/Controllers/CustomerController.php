<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\Payment;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Guarantee;
use App\Models\Broadcast\BroadcastLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Exports\CustomerExport;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $statusFilter = $request->input('status', 'active');

        if ($user->isSuperAdmin()) {
            if ($statusFilter === 'deactivated') {
                $query = Customer::onlyTrashed();
            } elseif ($statusFilter === 'all') {
                $query = Customer::withTrashed();
            } else {
                $query = Customer::query();
            }
        } else {
            $query = Customer::query();
            if ($statusFilter === 'deactivated' || $statusFilter === 'all') {
                $statusFilter = 'active';
            }
        }

        if ($user->role === 'sales') {
            $query->whereIn('branch_id', $user->accessible_branch_ids);
        } elseif (!$user->isSuperAdmin()) {
            $query->whereIn('branch_id', $user->accessible_branch_ids);
        }

        if ($request->filled('search')) {
            $searchRaw = $request->string('search')->trim()->value();
            $search = mb_strtolower($searchRaw);
            $phoneSearch = preg_replace('/\D+/', '', (string) $searchRaw);
            $phoneSearch = $this->normalizePhone($phoneSearch);

            $query->where(function ($q) use ($search, $phoneSearch, $searchRaw) {
                if ($phoneSearch !== '') {
                    $q->where('phone', 'like', "%{$phoneSearch}%");
                }
                $q->orWhere('phone', 'like', "%{$searchRaw}%");
                $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('bl_status')) {
            $blStatus = $request->input('bl_status');
            if ($blStatus === 'normal') {
                $query->where('is_blacklisted', false);
            } elseif ($blStatus === 'blacklisted') {
                $query->where('is_blacklisted', true);
            }
        }

        $customers = $query
            ->withCount('rentals')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $deactivatedCount = 0;
        if ($user->isSuperAdmin()) {
            $deactivatedQuery = Customer::onlyTrashed();
            if ($user->role === 'sales') {
                $deactivatedQuery->where('user_id', $user->id);
            } elseif (!$user->isSuperAdmin() && $user->branch_access_type === 'specific') {
                $deactivatedQuery->whereIn('branch_id', $user->accessible_branch_ids);
            }
            $deactivatedCount = $deactivatedQuery->count();
        }

        return view('customers.index', compact('customers', 'deactivatedCount', 'statusFilter'));
    }

    public function search(Request $request)
    {
        return $this->index($request);
    }

    public function create()
    {
        $user = Auth::user();
        if (!$user || ($user->role !== 'super_admin' && $user->role !== 'admin_toko' && $user->role !== 'sales')) {
            abort(403, 'Unauthorized action.');
        }
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || ($user->role !== 'super_admin' && $user->role !== 'admin_toko' && $user->role !== 'sales')) {
            abort(403, 'Unauthorized action.');
        }
        $request->merge(['phone' => $this->normalizePhone($request->input('phone'))]);

        $phone = $request->input('phone');
        $existingActive = Customer::where('phone', $phone)->first();
        $existingDeleted = Customer::withTrashed()->where('phone', $phone)->whereNotNull('deleted_at')->first();

        if ($existingDeleted) {
            return back()
                ->withInput()
                ->with('deleted_customer', [
                    'id' => $existingDeleted->id,
                    'name' => $existingDeleted->name,
                    'phone' => $existingDeleted->phone,
                    'deleted_at' => $existingDeleted->deleted_at ? $existingDeleted->deleted_at->format('d M Y H:i') : '-',
                ])
                ->withErrors(['phone' => "Nomor HP ini sudah pernah terdaftar (direkam sebagai \"{$existingDeleted->name}\" dan telah dinonaktifkan)."]);
        }

        if ($existingActive) {
            return back()->withInput()->withErrors([
                'phone' => "Nomor HP ini sudah terdaftar atas nama \"{$existingActive->name}\"",
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20', Rule::unique('customers', 'phone')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string'],
        ], ['phone.unique' => "Nomor HP ini sudah terdaftar"]);

        $data = $validated;
        $data['is_blacklisted'] = false;

        if (Auth::check()) {
            $user = Auth::user();
            if (isset($user->branch_id) && $user->branch_id !== null) {
                $data['branch_id'] = $user->branch_id;
            } elseif ($user->isSuperAdmin()) {
                $branchId = $request->input('branch_id');
                if ($branchId && Branch::where('id', $branchId)->where('is_active', true)->exists()) {
                    $data['branch_id'] = (int) $branchId;
                } else {
                    $defaultBranch = Branch::where('is_active', true)->orderBy('id')->first();
                    if (!$defaultBranch) {
                        abort(400, 'Tidak ada cabang aktif. Buat cabang terlebih dahulu.');
                    }
                    $data['branch_id'] = $defaultBranch->id;
                }
            }
            if ($user->role === 'sales') {
                $data['user_id'] = $user->id;
                if (empty($data['branch_id']) && $user->accessible_branch_ids !== []) {
                    $data['branch_id'] = $user->accessible_branch_ids[0];
                }
            }
        }
        $customer = Customer::create($data);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ],
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan.');
    }

    public function show(Customer $customer)
    {
        $user = Auth::user();
        if (!$user || ($user->role !== 'super_admin' && $user->role !== 'admin_toko' && $user->role !== 'sales')) {
            abort(403, 'Unauthorized action.');
        }
        abort_unless($user->isSuperAdmin() || $user->canAccessBranch((int) $customer->branch_id), 403);
        $customer->load(['rentals' => function ($q) { $q->latest()->with(['items.product']); }]);
        $customer->load('rentals.items');
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);
        return view('customers.create', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $user = Auth::user();
        $request->merge(['phone' => $this->normalizePhone($request->input('phone'))]);
        $phone = $request->input('phone');

        $existingDeleted = Customer::withTrashed()
            ->where('phone', $phone)
            ->whereNotNull('deleted_at')
            ->where('id', '!=', $customer->id)
            ->first();

        if ($existingDeleted) {
            return back()->withInput()->withErrors([
                'phone' => "Nomor HP ini sudah pernah terdaftar (direkam sebagai \"{$existingDeleted->name}\" dan telah dinonaktifkan).",
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20', Rule::unique('customers', 'phone')->ignore($customer->id)->whereNull('deleted_at')],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $validated;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isAdminToko() || $user->isSales()) {
                $data['branch_id'] = $customer->branch_id;
            } elseif (isset($user->branch_id) && $user->branch_id !== null) {
                $data['branch_id'] = $user->branch_id;
            } elseif ($user->isSuperAdmin()) {
                $branchId = $request->input('branch_id');
                if ($branchId && Branch::where('id', $branchId)->where('is_active', true)->exists()) {
                    $data['branch_id'] = (int) $branchId;
                } else {
                    $defaultBranch = Branch::where('is_active', true)->orderBy('id')->first();
                    if (!$defaultBranch) {
                        abort(400, 'Tidak ada cabang aktif. Buat cabang terlebih dahulu.');
                    }
                    $data['branch_id'] = $defaultBranch->id;
                }
            }
        }
        $customer->update($data);
        return redirect()->route('customers.show', $customer)->with('success', 'Customer berhasil diperbarui.');
    }

    public function toggleBlacklist(Request $request, Customer $customer)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $customer->is_blacklisted = !$customer->is_blacklisted;
        $customer->blacklist_reason = $customer->is_blacklisted ? $request->input('reason') : null;
        $customer->save();
        return back()->with('success', 'Status blacklist berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $hasActiveRental = $customer->rentals()
            ->whereIn('rental_status', ['waiting', 'active', 'overdue'])
            ->exists();

        if ($hasActiveRental) {
            return back()->with('error', 'Customer tidak dapat dinonaktifkan karena masih memiliki transaksi sewa yang sedang berlangsung.');
        }

        $customerName = $customer->name;

        BroadcastLog::where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->update([
                'customer_id' => null,
                'recipient_name' => $customer->name,
            ]);

        $oldValues = $customer->getAttributes();
        $customer->delete();

        $description = \App\Services\ActivityLogFormatter::format(
            $oldValues,
            null,
            Customer::class,
            $customer->id,
            'delete_customer'
        );

        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => $customer->branch_id,
            'action' => 'delete_customer',
            'model_type' => Customer::class,
            'model_id' => $customer->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        return redirect()->route('customers.index')->with('success', "Customer {$customerName} berhasil dinonaktifkan.");
    }

    public function forceDestroy(Customer $customer)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        abort_unless($user->role !== 'sales', 403);

        if (!$customer->trashed()) {
            return back()->with('error', 'Customer ini masih aktif. Nonaktifkan terlebih dahulu sebelum menghapus permanen.');
        }

        $hasActiveRental = $customer->rentals()
            ->whereIn('rental_status', ['waiting', 'active', 'overdue'])
            ->exists();
        $hasPayments = Payment::whereHas('rental', fn($q) => $q->where('customer_id', $customer->id)->whereIn('rental_status', ['waiting', 'active', 'overdue']))->exists();
        $hasGuarantees = Guarantee::whereHas('rental', fn($q) => $q->where('customer_id', $customer->id)->whereIn('rental_status', ['waiting', 'active', 'overdue']))->exists();

        if ($hasActiveRental || $hasPayments || $hasGuarantees) {
            $parts = [];
            if ($hasActiveRental) $parts[] = 'penyewaan';
            if ($hasPayments)     $parts[] = 'pembayaran';
            if ($hasGuarantees)   $parts[] = 'jaminan';
            return back()->with('error', 'Customer tidak dapat dihapus permanen karena masih memiliki riwayat ' . implode(', ', $parts) . '.');
        }

        $customerName = $customer->name;

        BroadcastLog::where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->update([
                'customer_id' => null,
                'recipient_name' => $customer->name,
            ]);

        $oldValues = $customer->getAttributes();

        try {
            $customer->forceDelete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->with('error', 'Customer tidak dapat dihapus permanen karena masih terhubung dengan data transaksi lain di database.');
            }
            throw $e;
        }

        $description = \App\Services\ActivityLogFormatter::format(
            $oldValues,
            null,
            Customer::class,
            $customer->id,
            'delete_customer'
        );

        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
            'action' => 'delete_customer',
            'model_type' => Customer::class,
            'model_id' => $customer->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        return redirect()->route('customers.index')->with('success', "Customer {$customerName} berhasil dihapus permanen.");
    }

    public function restore(Customer $customer)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        if (!$customer->trashed()) {
            return back()->with('error', 'Customer ini tidak dalam status nonaktif.');
        }

        $oldValues = $customer->getAttributes();
        $customer->restore();
        $newValues = $customer->getAttributes();

        $description = \App\Services\ActivityLogFormatter::format(
            $oldValues,
            $newValues,
            Customer::class,
            $customer->id,
            'restore_customer'
        );

        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
            'action' => 'restore_customer',
            'model_type' => Customer::class,
            'model_id' => $customer->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        return redirect()->route('customers.show', $customer)
            ->with('success', "Customer {$customer->name} berhasil dipulihkan.");
    }

    public function export()
    {
        $user = Auth::user();
        abort_if(!$user || ($user->role !== 'super_admin' && $user->role !== 'admin_toko'), 403);

        $fileName = 'Data_Customer_MaisonSewa_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new CustomerExport, $fileName);
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '620')) {
            $digits = '62' . substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif ($digits !== '' && !str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }
        return $digits;
    }
}
