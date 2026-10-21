<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $branches = Branch::withCount(['users', 'products', 'rentals'])
            ->orderBy('name')
            ->get();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        return view('branches.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:20|unique:branches,code',
            'address'   => 'nullable|string',
            'phone'     => 'nullable|regex:/^[0-9]+$/|max:20',
            'email'     => 'nullable|email|max:100',
            'city'      => 'nullable|string|max:100',
            'province'  => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        Branch::create([
            'name'      => $request->name,
            'code'      => strtoupper($request->code),
            'address'   => $request->address ?? '',
            'phone'     => $request->phone,
            'email'     => $request->email,
            'city'      => $request->city,
            'province'  => $request->province,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('branches.index')
            ->with('success', 'Cabang berhasil ditambahkan.');
    }

    public function show(Branch $branch)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $branch->loadCount(['users', 'products', 'rentals']);
        return view('branches.show', compact('branch'));
    }

    public function edit(Branch $branch)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:20|unique:branches,code,' . $branch->id,
            'address'   => 'nullable|string',
            'phone'     => 'nullable|regex:/^[0-9]+$/|max:20',
            'email'     => 'nullable|email|max:100',
            'city'      => 'nullable|string|max:100',
            'province'  => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name', 'code', 'address', 'phone', 'email', 'city', 'province', 'is_active']);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        foreach (['address', 'phone', 'email', 'city', 'province'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = $branch->$field;
            }
        }

        $branch->update($data);

        return redirect()->route('branches.index')
            ->with('success', 'Cabang berhasil diperbarui.');
    }

    public function destroy(Branch $branch)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        if ($branch->users()->count() > 0) {
            return back()->with('error', 'Cabang tidak dapat dihapus permanen karena masih memiliki pengguna.');
        }

        if ($branch->rentals()->count() > 0) {
            return back()->with('error', 'Cabang tidak dapat dihapus permanen karena masih memiliki data rental.');
        }

        $branch->forceDelete();

        return redirect()->route('branches.index')
            ->with('success', 'Cabang berhasil dihapus permanen.');
    }
}
