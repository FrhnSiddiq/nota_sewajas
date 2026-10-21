<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Poin insentif sales per produk: diberikan saat serah terima & saat pengembalian.
            // Default 3 (nilai poin serah lama) untuk semua produk yang sudah ada.
            $table->unsignedSmallInteger('points')->default(3)->after('deposit_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
