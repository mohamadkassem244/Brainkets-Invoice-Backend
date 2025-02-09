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
        Schema::create('ag_attachment', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 50);
            $table->unsignedBigInteger('row_id');
            $table->integer('type');
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255);
            $table->string('file_extension', 20);
            $table->boolean('cdn_uploaded')->default(0);
            $table->string('file_size', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ag_attachment');
    }
};
