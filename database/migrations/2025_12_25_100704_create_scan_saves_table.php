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
        Schema::create('scan_saves', function (Blueprint $table) {
            $table->id();
            $table->foreign('scan_id')->references('id')->on('scans')->onDelete('cascade');
            $table->enum('type', ['FULL', 'PARTIAL'])->default('FULL');
            $table->array('img_urls')->nullable();
            $table->string('product_name')->nullable();
            $table->float('rating')->nullable();
            $table->integer('count_purchase')->nullable();
            $table->float('price')->nullable();
            $table->string('product_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_saves');
    }
};
