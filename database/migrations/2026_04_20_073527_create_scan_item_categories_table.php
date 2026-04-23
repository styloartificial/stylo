<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('scans')->onDelete('cascade');
            $table->foreignId('item_category_id')->constrained('m_scan_categories')->onDelete('cascade');
            $table->enum('type', ['item', 'occasion', 'style', 'hijab']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_item_categories');
    }
};