<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('branches')) {
            return;
        }

        DB::transaction(function () {
            $users = DB::table('users')->get();

            foreach ($users as $user) {
                if ($user->role === 'super_admin') {
                    DB::table('users')->where('id', $user->id)->update([
                        'branch_access_type' => 'all',
                    ]);
                    continue;
                }

                if ($user->branch_id) {
                    DB::table('users')->where('id', $user->id)->update([
                        'branch_access_type' => 'specific',
                    ]);

                    DB::table('user_branch_access')->updateOrInsert([
                        'user_id' => $user->id,
                        'branch_id' => $user->branch_id,
                    ]);
                } else {
                    DB::table('users')->where('id', $user->id)->update([
                        'branch_access_type' => 'all',
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('user_branch_access')->whereIn('user_id', function ($query) {
            $query->select('id')->from('users');
        })->delete();

        DB::table('users')->update(['branch_access_type' => null]);
    }
};
