<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->json('channels')->nullable()->after('provider');
        });

        DB::table('broadcast_campaigns')->whereNull('channels')->update([
            'channels' => json_encode(['whatsapp']),
        ]);

        if (Schema::hasColumn('notifications', 'user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropColumn('channels');
        });

        if (!Schema::hasColumn('notifications', 'user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });

            DB::table('notifications')->where('notifiable_type', \App\Models\User::class)->update([
                'user_id' => DB::raw('notifiable_id'),
            ]);
        }
    }
};
