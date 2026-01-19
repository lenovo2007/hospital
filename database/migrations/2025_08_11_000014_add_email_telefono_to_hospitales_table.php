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
                if (!Schema::hasColumn('hospitales', 'email')) {
                    $table->string('email')->nullable()->after('rif');
                }
                if (!Schema::hasColumn('hospitales', 'telefono')) {
                    $table->string('telefono', 50)->nullable()->after('email');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hospitales')) {
            Schema::table('hospitales', function (Blueprint $table) {
                if (Schema::hasColumn('hospitales', 'telefono')) {
                    $table->dropColumn('telefono');
                }
                if (Schema::hasColumn('hospitales', 'email')) {
                    $table->dropColumn('email');
                }
            });
        }
    }
};
