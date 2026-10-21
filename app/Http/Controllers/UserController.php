<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Rental;
use App\Models\Payment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class UserController extends Controller
{
    // Daftar role tersedia (tanpa Spatie)
    private array $roles = ['super_admin', 'admin_toko', 'sales'];

    public function index(Request $request)
    {
        $users = User::with('branch')
            ->when($request->search, fn($q) => $q->where(fn($search) => $search
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")))
            ->when($request->role,   fn($q) => $q->where('role', $request->role))
            ->when($request->branch, fn($q) => $q->where('branch_id', $request->branch))
            ->latest()
            ->paginate(15)->withQueryString();

        $branches = Branch::where('is_active', true)->get();
        $roles    = $this->roles;
        $roleCounts = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return view('users.index', compact('users', 'branches', 'roles', 'roleCounts'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->get();
        $roles    = $this->roles;
        return view('users.create', compact('branches', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'email'              => 'required|email|unique:users,email',
            'password'           => 'required|string|min:8|confirmed',
            'phone'              => 'nullable|regex:/^[0-9]+$/|max:20',
            'branch_id'          => 'nullable|exists:branches,id',
            'role'               => 'required|in:super_admin,admin_toko,sales',
            'avatar'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'branch_access_type' => 'nullable|in:all,specific',
            'branch_ids'         => 'nullable|array',
            'branch_ids.*'       => 'exists:branches,id',
        ]);

        if (in_array($data['role'], ['super_admin', 'admin_toko'], true)) {
            $data['branch_access_type'] = 'all';
            if ($data['role'] === 'admin_toko' && empty($data['branch_id'])) {
                return back()->withInput()->withErrors([
                    'branch_id' => 'Pilih cabang utama untuk admin toko.',
                ]);
            }
            unset($data['branch_ids']);
        } elseif ($data['role'] === 'sales') {
            $data['branch_access_type'] = 'specific';
            if (empty($data['branch_id'])) {
                return back()->withInput()->withErrors([
                    'branch_id' => 'Pilih satu cabang untuk user sales.',
                ]);
            }
            $data['branch_ids'] = [$data['branch_id']];
        }

        if (($data['branch_access_type'] ?? null) === 'specific' && empty($data['branch_ids'])) {
            return back()->withInput()->withErrors([
                'branch_ids' => 'Pilih minimal 1 cabang untuk tipe akses cabang tertentu.',
            ]);
        }

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'name'               => $data['name'],
            'email'              => $data['email'],
            'password'           => Hash::make($data['password']),
            'phone'              => $data['phone'] ?? null,
            'branch_id'          => $data['role'] === 'super_admin' ? null : ($data['branch_id'] ?? ($data['branch_ids'][0] ?? null)),
            'role'               => $data['role'],
            'avatar'             => $avatarPath,
            'is_active'          => true,
            'branch_access_type' => $data['branch_access_type'],
        ]);

        if ($data['role'] === 'sales' && !empty($data['branch_ids'])) {
            $user->accessibleBranches()->sync($data['branch_ids']);
        }

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} berhasil ditambahkan!");
    }

    public function edit(User $user)
    {
        $branches = Branch::where('is_active', true)->get();
        $roles    = $this->roles;
        return view('users.edit', compact('user', 'branches', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'email'              => "required|email|unique:users,email,{$user->id}",
            'password'           => 'nullable|string|min:8|confirmed',
            'phone'              => 'nullable|regex:/^[0-9]+$/|max:20',
            'branch_id'          => 'nullable|exists:branches,id',
            'role'               => 'required|in:super_admin,admin_toko,sales',
            'avatar'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'branch_access_type' => 'nullable|in:all,specific',
            'branch_ids'         => 'nullable|array',
            'branch_ids.*'       => 'exists:branches,id',
        ]);

        if (in_array($data['role'], ['super_admin', 'admin_toko'], true)) {
            $data['branch_access_type'] = 'all';
            if ($data['role'] === 'admin_toko' && empty($data['branch_id'])) {
                return back()->withInput()->withErrors([
                    'branch_id' => 'Pilih cabang utama untuk admin toko.',
                ]);
            }
            unset($data['branch_ids']);
        } elseif ($data['role'] === 'sales') {
            $data['branch_access_type'] = 'specific';
            if (empty($data['branch_id'])) {
                return back()->withInput()->withErrors([
                    'branch_id' => 'Pilih satu cabang untuk user sales.',
                ]);
            }
            $data['branch_ids'] = [$data['branch_id']];
        }

        if (($data['branch_access_type'] ?? null) === 'specific' && empty($data['branch_ids'])) {
            return back()->withInput()->withErrors([
                'branch_ids' => 'Pilih minimal 1 cabang untuk tipe akses cabang tertentu.',
            ]);
        }

        $avatarPath = $user->avatar;
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                \Storage::disk('public')->delete($user->avatar);
            }
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $updateData = [
            'name'               => $data['name'],
            'email'              => $data['email'],
            'phone'              => $data['phone'] ?? null,
            'branch_id'          => $data['role'] === 'super_admin' ? null : ($data['branch_id'] ?? ($data['branch_ids'][0] ?? null)),
            'role'               => $data['role'],
            'avatar'             => $avatarPath,
            'password'           => !empty($data['password']) ? Hash::make($data['password']) : $user->password,
            'branch_access_type' => $data['branch_access_type'],
        ];

        $user->update($updateData);

        if ($data['role'] === 'sales' && !empty($data['branch_ids'])) {
            $user->accessibleBranches()->sync($data['branch_ids']);
        } elseif ($data['role'] === 'super_admin') {
            $user->accessibleBranches()->detach();
        }

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} berhasil diperbarui!");
    }

    public function show(User $user)
    {
        $user->load(['branch', 'rentals' => fn($q) => $q->latest()->take(10)]);
        $rentalCount = Rental::where('created_by', $user->id)->count();
        $activityLogs = ActivityLog::where('user_id', $user->id)->latest()->take(10)->get();
        return view('users.show', compact('user', 'rentalCount', 'activityLogs'));
    }

    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $oldActive = $user->is_active;
        $user->update(['is_active' => !$oldActive]);

        $action = $user->is_active ? 'activate_user' : 'deactivate_user';
        $description = \App\Services\ActivityLogFormatter::format(
            ['is_active' => $oldActive],
            ['is_active' => $user->is_active],
            User::class,
            $user->id,
            $action
        );

        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => $description,
            'old_values' => ['is_active' => $oldActive],
            'new_values' => ['is_active' => $user->is_active],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "User {$user->name} berhasil {$status}.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        if ($user->isSuperAdmin()) {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'Tidak bisa menghapus Super Admin terakhir.');
            }
        }

        $hasRentalsCreated  = Rental::where('created_by', $user->id)->exists();
        $hasRentalsReturned = Rental::where('returned_by', $user->id)->exists();
        $hasPayments        = Payment::where('received_by', $user->id)->exists();
        $hasRentals         = $hasRentalsCreated || $hasRentalsReturned;

        if ($hasRentals || $hasPayments) {
            $parts = [];
            if ($hasRentalsCreated)  $parts[] = 'penyewaan dibuat';
            if ($hasRentalsReturned) $parts[] = 'penyewaan dikembalikan';
            if ($hasPayments)        $parts[] = 'pembayaran';
            return back()->with('error', 'User tidak dapat dihapus permanen karena masih memiliki riwayat ' . implode(', ', $parts) . '.');
        }

        $userName = $user->name;

        ActivityLog::where('user_id', $user->id)->update(['user_id' => null]);

        $oldValues = $user->getAttributes();

        try {
            $user->forceDelete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->with('error', 'User tidak dapat dihapus permanen karena masih terhubung dengan data transaksi lain di database.');
            }
            throw $e;
        }

        $description = \App\Services\ActivityLogFormatter::format(
            $oldValues,
            null,
            User::class,
            $user->id,
            'delete_user'
        );

        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
            'action' => 'delete_user',
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', "User {$userName} berhasil dihapus permanen.");
    }
}
