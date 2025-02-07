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
        Schema::create('in_sales_invoice_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('in_sales_invoice')->nullOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('tax_rate', 10, 2)->default(0);
            $table->enum('tax_method', ['inclusive', 'exclusive'])->default('inclusive');
            $table->decimal('discount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_sales_invoice_item');
    }
};
