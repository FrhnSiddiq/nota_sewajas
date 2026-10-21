<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('laundries', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
            $table->unsignedBigInteger('produk_id')->nullable()->change();
            $table->foreign('produk_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('laundries', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
            $table->unsignedBigInteger('produk_id')->nullable(false)->change();
            $table->foreign('produk_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
