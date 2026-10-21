<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
protected $policies = [
        \App\Models\Product::class => \App\Policies\ProductPolicy::class,
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Category::class => \App\Policies\CategoryPolicy::class,
        \App\Models\Branch::class => \App\Policies\BranchPolicy::class,
        \App\Models\Rental::class => \App\Policies\RentalPolicy::class,
        \App\Models\RentalHistory::class => \App\Policies\RentalHistoryPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // ─── Super Admin bypass semua gate ────────────────────────────────────
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) return true;
        });

        // ─── Gate: Laporan ────────────────────────────────────────────────────

        // Lihat laporan semua branch (hanya super_admin via Gate::before)
        Gate::define('laporan.view-all',    fn ($user) => $user->isSuperAdmin());

        // Export PDF & Excel semua branch
        Gate::define('laporan.export-pdf',  fn ($user) => $user->isSuperAdmin());
        Gate::define('laporan.export-excel',fn ($user) => $user->isSuperAdmin());

        // ─── Gate: Rental ─────────────────────────────────────────────────────

        // Buat rental baru (admin_toko & sales bisa)
        Gate::define('rental.create', fn ($user) => $user->canManage() || $user->isSales());

        // Edit & hapus rental (hanya admin_toko ke atas)
        Gate::define('rental.edit',   fn ($user) => $user->canManage());
        Gate::define('rental.delete', fn ($user) => $user->canManage());

        // Proses refund/pembatalan (hanya admin_toko ke atas)
        Gate::define('rental.cancel', fn ($user) => $user->canManage());

        // ─── Gate: Manajemen ──────────────────────────────────────────────────

        // Kelola branch (hanya super_admin via Gate::before)
        Gate::define('branch.manage',  fn ($user) => $user->isSuperAdmin());

        // Kelola user/admin (hanya super_admin via Gate::before)
        Gate::define('user.manage',    fn ($user) => $user->isSuperAdmin());

        // Lihat activity log semua branch
        Gate::define('activity.view-all', fn ($user) => $user->isSuperAdmin());

        // ─── Gate: Laundry ────────────────────────────────────────────────────

        // Update status laundry (admin_toko & sales)
        Gate::define('laundry.update-status', fn ($user) => in_array($user->role, ['super_admin', 'admin_toko', 'sales']));
    }
}
