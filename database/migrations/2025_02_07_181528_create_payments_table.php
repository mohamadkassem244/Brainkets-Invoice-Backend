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
        Schema::create('payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customer')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('invoice_id')->nullable()->constrained('in_sales_invoice')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('journal')->nullable()->constrained('account')->nullOnDelete()->cascadeOnUpdate();
            $table->date('date');
            $table->enum('payment_type', ['send', 'receive'])->default('receive');
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->integer('created_by')->default(1);
            $table->integer('updated_by')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
