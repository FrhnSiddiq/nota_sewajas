<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // semua role bisa lihat list produk
    }

    public function view(User $user, Product $product): bool
    {
        if ($user->isSuperAdmin()) return true;
        return $user->canAccessBranch($product->branch_id);
    }

    public function create(User $user): bool
    {
        // Sales tidak bisa buat produk
        return in_array($user->role, ['super_admin', 'admin_toko']);
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->isSuperAdmin()) return true;
        if ($user->role === 'sales') return false;
        return $user->canAccessBranch($product->branch_id);
    }

    public function delete(User $user, Product $product): bool
    {
        // Sales tidak boleh menghapus produk
        if ($user->isSuperAdmin()) return true;
        if ($user->role === 'sales') return false;
        return $user->canAccessBranch($product->branch_id);
    }

}