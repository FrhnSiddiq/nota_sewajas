<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->integer('stock_maintenance')->default(0)->after('stock_available');
            $table->integer('stock_lost')->default(0)->after('stock_maintenance');
        });

        Schema::create('product_stock_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_sizes')->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained('rentals')->nullOnDelete();
            $table->foreignId('rental_item_id')->nullable()->constrained('rental_items')->nullOnDelete();
            $table->string('size', 20);
            $table->string('condition', 20)->comment('rusak_ringan, rusak_berat, hilang');
            $table->integer('quantity')->default(1);
            $table->decimal('damage_fee', 12, 2)->default(0);
            $table->string('status', 20)->default('maintenance')->comment('maintenance, restored, disposed');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_maintenances');
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn(['stock_maintenance', 'stock_lost']);
        });
    }
};
