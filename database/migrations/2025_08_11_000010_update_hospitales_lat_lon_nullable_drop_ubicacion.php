<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hospitales')) {
            Schema::table('hospitales', function (Blueprint $table) {
                if (!Schema::hasColumn('hospitales', 'lat')) {
                    $table->decimal('lat', 10, 7)->nullable()->after('rif');
                } else {
                    $table->decimal('lat', 10, 7)->nullable()->change();
                }
                if (!Schema::hasColumn('hospitales', 'lon')) {
                    $table->decimal('lon', 10, 7)->nullable()->after('lat');
                } else {
                    $table->decimal('lon', 10, 7)->nullable()->change();
                }
                if (Schema::hasColumn('hospitales', 'ubicacion')) {
                    $table->dropColumn('ubicacion');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hospitales')) {
            Schema::table('hospitales', function (Blueprint $table) {
                if (Schema::hasColumn('hospitales', 'lon')) {
                    $table->dropColumn('lon');
                }
                if (Schema::hasColumn('hospitales', 'lat')) {
                    $table->dropColumn('lat');
                }
                if (!Schema::hasColumn('hospitales', 'ubicacion')) {
                    $table->json('ubicacion')->nullable()->after('rif');
                }
            });
        }
    }
};
