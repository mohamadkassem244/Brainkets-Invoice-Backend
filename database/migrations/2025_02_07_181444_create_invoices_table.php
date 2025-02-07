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
        Schema::create('in_sales_invoice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customer')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('currency_id')->nullable()->constrained('currency')->nullOnDelete()->cascadeOnUpdate();
            $table->string('reference')->unique();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'canceled'])->default('pending');
            $table->boolean('is_recurring')->default(false);
            $table->enum('repeat_cycle', ['daily', 'weekly', 'monthly', 'yearly'])->default('daily');
            $table->unsignedSmallInteger('create_before_days')->default(1);
            $table->decimal('tax_rate', 10, 2)->default(0);
            $table->enum('tax_method', ['inclusive', 'exclusive'])->default('inclusive');
            $table->decimal('shipping', 10, 2)->default(0);
            $table->text('note')->nullable();
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
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
        Schema::dropIfExists('in_sales_invoice');
    }
};
