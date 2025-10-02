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
        // Modificar tabla lotes_grupos
        Schema::table('lotes_grupos', function (Blueprint $table) {
            // Renombrar cantidad a cantidad_salida
            $table->renameColumn('cantidad', 'cantidad_salida');
        });

        Schema::table('lotes_grupos', function (Blueprint $table) {
            // Agregar nuevo campo cantidad_entrada
            $table->integer('cantidad_entrada')->default(0)->after('cantidad_salida');
        });

        // Modificar tabla movimientos_stock
        Schema::table('movimientos_stock', function (Blueprint $table) {
            // Renombrar cantidad a cantidad_salida
            $table->renameColumn('cantidad', 'cantidad_salida');
        });

        Schema::table('movimientos_stock', function (Blueprint $table) {
            // Agregar nuevo campo cantidad_entrada
            $table->integer('cantidad_entrada')->default(0)->after('cantidad_salida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios en lotes_grupos
        Schema::table('lotes_grupos', function (Blueprint $table) {
            $table->dropColumn('cantidad_entrada');
        });

        Schema::table('lotes_grupos', function (Blueprint $table) {
            $table->renameColumn('cantidad_salida', 'cantidad');
        });

        // Revertir cambios en movimientos_stock
        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->dropColumn('cantidad_entrada');
        });

        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->renameColumn('cantidad_salida', 'cantidad');
        });
    }
};
