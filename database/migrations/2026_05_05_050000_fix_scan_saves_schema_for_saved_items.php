<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scan_saves')) {
            return;
        }

        Schema::table('scan_saves', function (Blueprint $table) {
            if (!Schema::hasColumn('scan_saves', 'img_url')) {
                $table->text('img_url')->nullable();
            }

            if (!Schema::hasColumn('scan_saves', 'is_partial')) {
                $table->boolean('is_partial')->default(false);
            }
        });

        $driver = DB::getDriverName();

        if (Schema::hasColumn('scan_saves', 'type') && Schema::hasColumn('scan_saves', 'is_partial')) {
            if ($driver === 'pgsql') {
                DB::statement("UPDATE scan_saves SET is_partial = CASE WHEN type = 'PARTIAL' THEN TRUE ELSE FALSE END");
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("UPDATE scan_saves SET is_partial = CASE WHEN type = 'PARTIAL' THEN 1 ELSE 0 END");
            }
        }

        if (Schema::hasColumn('scan_saves', 'img_urls') && Schema::hasColumn('scan_saves', 'img_url')) {
            if ($driver === 'pgsql') {
                DB::statement('UPDATE scan_saves SET img_url = COALESCE(img_url, img_urls->>0) WHERE img_url IS NULL AND img_urls IS NOT NULL');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("UPDATE scan_saves SET img_url = COALESCE(img_url, JSON_UNQUOTE(JSON_EXTRACT(img_urls, '$[0]'))) WHERE img_url IS NULL AND img_urls IS NOT NULL");
            }
        }

        Schema::table('scan_saves', function (Blueprint $table) {
            if (Schema::hasColumn('scan_saves', 'img_urls')) {
                $table->dropColumn('img_urls');
            }

            if (Schema::hasColumn('scan_saves', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('scan_saves')) {
            return;
        }

        Schema::table('scan_saves', function (Blueprint $table) {
            if (Schema::hasColumn('scan_saves', 'img_url')) {
                $table->dropColumn('img_url');
            }

            if (Schema::hasColumn('scan_saves', 'is_partial')) {
                $table->dropColumn('is_partial');
            }

            if (!Schema::hasColumn('scan_saves', 'type')) {
                $table->string('type')->nullable();
            }

            if (!Schema::hasColumn('scan_saves', 'img_urls')) {
                $table->json('img_urls')->nullable();
            }
        });
    }
};
