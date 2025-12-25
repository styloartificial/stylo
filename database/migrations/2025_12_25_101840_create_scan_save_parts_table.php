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
        Schema::create('scan_save_parts', function (Blueprint $table) {
            $table->id();
            $table->foreign('scan_save_id')->references('id')->on('scan_saves')->onDelete('cascade');
            $table->array('img_urls');
            $table->string('product_name');
            $table->float('rating');
            $table->integer('count_purchase');
            $table->float('price');
            $table->string('product_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_save_parts');
    }
};
