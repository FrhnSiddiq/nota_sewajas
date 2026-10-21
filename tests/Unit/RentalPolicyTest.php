<?php

namespace Tests\Unit;

use App\Models\Rental;
use App\Models\User;
use App\Policies\RentalPolicy;
use PHPUnit\Framework\TestCase;

class RentalPolicyTest extends TestCase
{
    public function test_sales_can_view_rentals_in_their_assigned_branch(): void
    {
        $policy = new RentalPolicy();
        $sales = new class extends User {
            public array $branchAccess = [];

            public function canAccessBranch(int $branchId): bool
            {
                return $this->branchAccess[$branchId] ?? false;
            }
        };
        $sales->forceFill(['id' => 4, 'role' => User::ROLE_SALES]);
        $sales->branchAccess = [1 => true, 2 => false];
        $sameBranchRental = new Rental();
        $sameBranchRental->forceFill(['created_by' => 5, 'branch_id' => 1]);
        $otherBranchRental = new Rental();
        $otherBranchRental->forceFill(['created_by' => 4, 'branch_id' => 2]);

        $this->assertTrue($policy->view($sales, $sameBranchRental));
        $this->assertFalse($policy->view($sales, $otherBranchRental));
    }

    public function test_admin_toko_view_delegates_to_existing_branch_access(): void
    {
        $policy = new RentalPolicy();
        $admin = new class extends User {
            public array $branchAccess = [];

            public function canAccessBranch(int $branchId): bool
            {
                return $this->branchAccess[$branchId] ?? false;
            }
        };
        $admin->forceFill(['role' => User::ROLE_ADMIN_TOKO]);
        $admin->branchAccess = [2 => true, 3 => false];

        $inScopeRental = new Rental();
        $inScopeRental->forceFill(['branch_id' => 2, 'created_by' => 1]);
        $outOfScopeRental = new Rental();
        $outOfScopeRental->forceFill(['branch_id' => 3, 'created_by' => 1]);

        $this->assertTrue($policy->view($admin, $inScopeRental));
        $this->assertFalse($policy->view($admin, $outOfScopeRental));
    }

    public function test_super_admin_can_view_rentals_globally(): void
    {
        $policy = new RentalPolicy();
        $superAdmin = new User();
        $superAdmin->forceFill(['id' => 1, 'role' => User::ROLE_SUPER_ADMIN]);
        $rental = new Rental();
        $rental->forceFill(['created_by' => 4, 'branch_id' => 999]);

        $this->assertTrue($policy->view($superAdmin, $rental));
    }
}