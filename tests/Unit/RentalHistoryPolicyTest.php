<?php

namespace Tests\Unit;

use App\Models\RentalHistory;
use App\Models\User;
use App\Policies\RentalHistoryPolicy;
use PHPUnit\Framework\TestCase;

class RentalHistoryPolicyTest extends TestCase
{
    public function test_sales_can_view_history_from_their_branch_but_not_another_branch(): void
    {
        $sales = new class extends User {
            public array $branchAccess = [];

            public function canAccessBranch(int $branchId): bool
            {
                return $this->branchAccess[$branchId] ?? false;
            }
        };
        $sales->forceFill(['id' => 4, 'role' => User::ROLE_SALES]);
        $sales->branchAccess = [1 => true, 2 => false];

        $sameBranchHistory = new RentalHistory();
        $sameBranchHistory->forceFill(['created_by' => 5, 'branch_id' => 1]);
        $otherBranchHistory = new RentalHistory();
        $otherBranchHistory->forceFill(['created_by' => 4, 'branch_id' => 2]);

        $policy = new RentalHistoryPolicy();

        $this->assertTrue($policy->view($sales, $sameBranchHistory));
        $this->assertFalse($policy->view($sales, $otherBranchHistory));
    }
}
