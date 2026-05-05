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
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->text('img_url')->nullable()->change();
            $table->text('product_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->string('img_url')->nullable()->change();
            $table->string('product_url')->nullable()->change();
        });
    }
};
