<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            if (!Schema::hasColumn('scan_saves', 'product_name')) {
                $table->string('product_name')->nullable()->after('img_url');
            }
            if (!Schema::hasColumn('scan_saves', 'price')) {
                $table->float('price')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('scan_saves', 'rating')) {
                $table->float('rating')->nullable()->after('price');
            }
            if (!Schema::hasColumn('scan_saves', 'count_purchase')) {
                $table->integer('count_purchase')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('scan_saves', 'product_url')) {
                $table->string('product_url')->nullable()->after('count_purchase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'price', 'rating', 'count_purchase', 'product_url']);
        });
    }
};