<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('scan_saves')) {
            return;
        }

        $driver = DB::getDriverName();

        if (Schema::hasColumn('scan_saves', 'img_url')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE scan_saves ALTER COLUMN img_url TYPE text');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE scan_saves MODIFY img_url TEXT NULL');
            }
        }

        if (Schema::hasColumn('scan_saves', 'product_url')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE scan_saves ALTER COLUMN product_url TYPE text');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE scan_saves MODIFY product_url TEXT NULL');
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('scan_saves')) {
            return;
        }

        $driver = DB::getDriverName();

        if (Schema::hasColumn('scan_saves', 'img_url')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE scan_saves ALTER COLUMN img_url TYPE varchar(255)');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE scan_saves MODIFY img_url VARCHAR(255) NULL');
            }
        }

        if (Schema::hasColumn('scan_saves', 'product_url')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE scan_saves ALTER COLUMN product_url TYPE varchar(255)');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE scan_saves MODIFY product_url VARCHAR(255) NULL');
            }
        }
    }
};
