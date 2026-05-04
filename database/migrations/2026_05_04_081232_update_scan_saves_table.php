<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('img_url');
            $table->float('price')->nullable()->after('product_name');
            $table->float('rating')->nullable()->after('price');
            $table->integer('count_purchase')->nullable()->after('rating');
            $table->string('product_url')->nullable()->after('count_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'price', 'rating', 'count_purchase', 'product_url']);
        });
    }
};