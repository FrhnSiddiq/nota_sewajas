<?php

namespace App\Policies;

use App\Models\RentalHistory;
use App\Models\User;

class RentalHistoryPolicy
{
    public function view(User $user, RentalHistory $history): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdminToko()) {
            return $user->canAccessBranch((int) $history->branch_id);
        }

        if ($user->isSales()) {
            return $user->canAccessBranch((int) $history->branch_id);
        }

        return false;
    }
}
