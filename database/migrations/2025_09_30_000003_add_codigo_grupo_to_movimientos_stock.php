<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar columna codigo_grupo a movimientos_stock
     */
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_stock', 'codigo_grupo')) {
                $table->string('codigo_grupo', 50)->nullable()->after('estado');
            }
        });
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'codigo_grupo')) {
                $table->dropColumn('codigo_grupo');
            }
        });
    }
};
