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
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('ticket_id')->unique()->nullable();
            $table->string('title');
            $table->foreign('scan_category_id')->references('id')->on('m_scan_categories')->onDelete('set null');
            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED'])->default('PENDING');            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
