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
        Schema::table('user_details', function (Blueprint $table) {
            $table->foreignId('body_shape_id')->nullable()->constrained('m_body_shapes')->nullOnDelete()->after('skin_tone_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropForeign(['body_shape_id']);
            $table->dropColumn('body_shape_id');
        });
    }
};
