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
        Schema::create('save_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_save_id')->constrained('scan_saves')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('img_url');
            $table->decimal('rating', 3, 1)->default(0);
            $table->decimal('count_purchase', 15, 0)->default(0);
            $table->decimal('price', 15, 0)->default(0);
            $table->string('product_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('save_items');
    }
};
