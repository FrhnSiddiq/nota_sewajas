<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'branch_access_type',
        'total_points',
        'phone',
        'avatar',
        'is_active',
        'theme',
        'opt_out',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'opt_out'           => 'boolean',
    ];

    // ─── Roles Constants ─────────────────────────────────────────────────────

    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN_TOKO  = 'admin_toko';
    const ROLE_SALES       = 'sales';
    const ROLE_CUSTOMER    = 'customer';

    const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN_TOKO  => 'Admin Toko',
        self::ROLE_SALES       => 'Sales',
        self::ROLE_CUSTOMER    => 'Customer',
    ];

    // ─── Role Helpers ────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdminToko(): bool
    {
        return $this->role === self::ROLE_ADMIN_TOKO;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }
        return $this->role === $roles;
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function getBranchAccessTypeAttribute($value): string
    {
        return $this->isSuperAdmin() || $this->isAdminToko() ? 'all' : 'specific';
    }

    public function getRoleBadgeColorAttribute(): string
    {
        return match($this->role) {
            self::ROLE_SUPER_ADMIN => 'bg-amber-100 text-amber-800',
            self::ROLE_ADMIN_TOKO  => 'bg-blue-100 text-blue-800',
            self::ROLE_SALES       => 'bg-green-100 text-green-800',
            self::ROLE_CUSTOMER    => 'bg-purple-100 text-purple-800',
            default                => 'bg-gray-100 text-gray-800',
        };
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function accessibleBranches()
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access');
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'created_by');
    }

    public function customerRentals()
    {
        return $this->hasMany(Rental::class, 'customer_id');
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function chatsAsCustomer(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class, 'customer_id');
    }

    public function chatsAsSales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class, 'sales_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBranch(\Illuminate\Database\Eloquent\Builder $query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByRole(\Illuminate\Database\Eloquent\Builder $query, string $role)
    {
        return $query->where('role', $role);
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Notification::class, 'notifiable');
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=D6B98C&color=fff&size=128';
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($branchId, $this->accessible_branch_ids, true);
    }

    public function getAccessibleBranchIdsAttribute(): array
    {
        if ($this->isSuperAdmin()) {
            return Branch::pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->isAdminToko()) {
            return Branch::pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $branchIds = $this->accessibleBranches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();

        if ($branchIds !== []) {
            return $branchIds;
        }

        return $this->branch_id ? [(int) $this->branch_id] : [];
    }

    public function canManage(): bool
    {
        return $this->isSuperAdmin() || $this->isAdminToko();
    }
}
