<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rental_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->string('invoice_number', 50);
            $table->date('rental_date');
            $table->date('return_due_date');
            $table->date('actual_return_date')->nullable();
            $table->integer('duration_days');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('fine_amount', 12, 2)->default(0);
            $table->decimal('fine_paid_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('fine_status', ['none', 'unpaid', 'partial', 'paid'])->default('none');
            $table->text('notes')->nullable();
            $table->json('items_snapshot')->nullable(); // Snapshot of rental items with product details
            $table->json('guarantees_snapshot')->nullable(); // Snapshot of guarantees
            $table->json('payments_snapshot')->nullable(); // Snapshot of payments
            $table->timestamp('completed_at')->nullable(); // When rental was completed (returned)
            $table->timestamps();

            $table->index(['branch_id', 'completed_at']);
            $table->index('invoice_number');
            $table->index('customer_id');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_histories');
    }
};