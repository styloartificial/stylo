<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->string('source')->nullable()->after('product_url');
            $table->string('group_label')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('scan_saves', function (Blueprint $table) {
            $table->dropColumn(['source', 'group_label']);
        });
    }
};