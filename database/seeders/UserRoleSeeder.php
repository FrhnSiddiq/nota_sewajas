<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════╗');
        $this->command->info('║         UserRoleSeeder — Mulai          ║');
        $this->command->info('╚══════════════════════════════════════════╝');

        // ─── Pastikan minimal ada 2 Branch ───────────────────────────
        $branchPusat = Branch::firstOrCreate(
            ['code' => 'YGY01'],
            [
                'name'      => 'Toko Pusat - Yogyakarta',
                'address'   => 'Jl. Malioboro No. 88, Yogyakarta',
                'phone'     => '0274-123456',
                'email'     => 'pusat@sewajas.id',
                'city'      => 'Yogyakarta',
                'province'  => 'DI Yogyakarta',
                'is_active' => true,
            ]
        );

        $branchSolo = Branch::firstOrCreate(
            ['code' => 'SLO01'],
            [
                'name'      => 'Cabang Solo',
                'address'   => 'Jl. Slamet Riyadi No. 45, Solo',
                'phone'     => '0271-654321',
                'email'     => 'solo@sewajas.id',
                'city'      => 'Surakarta',
                'province'  => 'Jawa Tengah',
                'is_active' => true,
            ]
        );

        $this->command->info('Branch : ✓ ' . $branchPusat->name);
        $this->command->info('Branch : ✓ ' . $branchSolo->name);

        // ─── [1] Super Admin ─────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('── [1] Membuat Super Admin ──');

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sewajas.id'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('password'),
                'phone'     => '081234567890',
                'role'      => 'super_admin',
                'branch_id' => null,
                'is_active' => true,
            ]
        );
        $this->command->info('   ✓ ' . $superAdmin->name . ' <' . $superAdmin->email . '>');

        // ─── [2] Admin Toko ──────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('── [2] Membuat Admin Toko ──');

        $adminPusat = User::firstOrCreate(
            ['email' => 'admin.pusat@sewajas.id'],
            [
                'name'      => 'Admin Toko Pusat',
                'password'  => Hash::make('password'),
                'phone'     => '081298765432',
                'role'      => 'admin_toko',
                'branch_id' => $branchPusat->id,
                'is_active' => true,
            ]
        );
        $this->command->info('   ✓ ' . $adminPusat->name . ' <' . $adminPusat->email . '> → ' . $branchPusat->name);

        $adminSolo = User::firstOrCreate(
            ['email' => 'admin.solo@sewajas.id'],
            [
                'name'      => 'Admin Cabang Solo',
                'password'  => Hash::make('password'),
                'phone'     => '082187654321',
                'role'      => 'admin_toko',
                'branch_id' => $branchSolo->id,
                'is_active' => true,
            ]
        );
        $this->command->info('   ✓ ' . $adminSolo->name . ' <' . $adminSolo->email . '> → ' . $branchSolo->name);

        // ─── [3] Sales ───────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('── [3] Membuat Sales ──');

        $sales1 = User::firstOrCreate(
            ['email' => 'sales.pusat@sewajas.id'],
            [
                'name'      => 'Budi Santoso',
                'password'  => Hash::make('password'),
                'phone'     => '085312345678',
                'role'      => 'sales',
                'branch_id' => $branchPusat->id,
                'is_active' => true,
            ]
        );
        $this->command->info('   ✓ ' . $sales1->name . ' <' . $sales1->email . '> → ' . $branchPusat->name);

        $sales2 = User::firstOrCreate(
            ['email' => 'sales.solo@sewajas.id'],
            [
                'name'      => 'Andi Pratama',
                'password'  => Hash::make('password'),
                'phone'     => '085398765432',
                'role'      => 'sales',
                'branch_id' => $branchSolo->id,
                'is_active' => true,
            ]
        );
        $this->command->info('   ✓ ' . $sales2->name . ' <' . $sales2->email . '> → ' . $branchSolo->name);

        // ─── Summary ─────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════════════════╗');
        $this->command->info('║    AKUN SIAP PAKAI  (password semua: password)     ║');
        $this->command->info('╠══════════════════════════════════════════════════════╣');
        $this->command->info('║  SUPER ADMIN                                        ║');
        $this->command->info('║    superadmin@sewajas.id                            ║');
        $this->command->info('╠══════════════════════════════════════════════════════╣');
        $this->command->info('║  ADMIN TOKO                                         ║');
        $this->command->info('║    admin.pusat@sewajas.id  → Toko Pusat Yogyakarta ║');
        $this->command->info('║    admin.solo@sewajas.id   → Cabang Solo           ║');
        $this->command->info('╠══════════════════════════════════════════════════════╣');
        $this->command->info('║  SALES                                              ║');
        $this->command->info('║    sales.pusat@sewajas.id  → Toko Pusat Yogyakarta ║');
        $this->command->info('║    sales.solo@sewajas.id   → Cabang Solo           ║');
        $this->command->info('╚══════════════════════════════════════════════════════╝');
    }
}

