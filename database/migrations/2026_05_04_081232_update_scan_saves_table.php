<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->dropColumn(['type', 'img_urls', 'product_name', 'rating', 'count_purchase', 'price', 'product_url']);
            $table->string('img_url')->nullable()->after('scan_id');
            $table->boolean('is_partial')->default(0)->after('img_url');
        });
    }

    public function down(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->dropColumn(['img_url', 'is_partial']);
            $table->enum('type', ['FULL', 'PARTIAL'])->default('FULL');
            $table->json('img_urls')->nullable();
            $table->string('product_name')->nullable();
            $table->float('rating')->nullable();
            $table->integer('count_purchase')->nullable();
            $table->float('price')->nullable();
            $table->string('product_url')->nullable();
        });
    }
};