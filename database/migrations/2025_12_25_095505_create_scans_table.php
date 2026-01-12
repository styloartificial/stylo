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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('ticket_id')->unique()->nullable();
            $table->string('title');
            $table->string('img_url');
            $table->foreignId('scan_category_id')->nullable()->constrained('m_scan_categories')->onDelete('set null');
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
