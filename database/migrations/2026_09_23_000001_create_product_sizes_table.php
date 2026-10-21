<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('size', 20)->comment('XS, S, M, L, XL, XXL ...');
            $table->integer('stock_total')->default(0);
            $table->integer('stock_available')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};
