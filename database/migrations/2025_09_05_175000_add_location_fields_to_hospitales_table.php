<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->string('dependencia')->nullable()->after('direccion');
            $table->string('estado')->nullable()->after('dependencia');
            $table->string('municipio')->nullable()->after('estado');
            $table->string('parroquia')->nullable()->after('municipio');
        });
    }

    public function down(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            if (Schema::hasColumn('hospitales', 'parroquia')) {
                $table->dropColumn('parroquia');
            }
            if (Schema::hasColumn('hospitales', 'municipio')) {
                $table->dropColumn('municipio');
            }
            if (Schema::hasColumn('hospitales', 'estado')) {
                $table->dropColumn('estado');
            }
            if (Schema::hasColumn('hospitales', 'dependencia')) {
                $table->dropColumn('dependencia');
            }
        });
    }
};
