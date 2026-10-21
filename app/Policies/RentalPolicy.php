<?php

namespace App\Policies;

use App\Models\Rental;
use App\Models\User;

class RentalPolicy
{
    public function view(User $user, Rental $rental): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdminToko()) {
            return $user->canAccessBranch((int) $rental->branch_id);
        }

        if ($user->isSales()) {
            return $user->canAccessBranch((int) $rental->branch_id);
        }

        return false;
    }
}