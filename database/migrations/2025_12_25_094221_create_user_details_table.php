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
        Schema::create('user_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('gender', ['MALE', 'FEMALE'])->default('MALE');
            $table->date('date_of_birth')->nullable();
            $table->float('height')->nullable();
            $table->float('weight')->nullable();
            $table->foreignId('skin_tone_id')->nullable()->constrained('m_skin_tones')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};
