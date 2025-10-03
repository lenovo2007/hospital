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
        Schema::table('movimientos_stock', function (Blueprint $table) {
            // Renombrar campos
            $table->renameColumn('cantidad_salida', 'cantidad_salida_total');
            $table->renameColumn('cantidad_entrada', 'cantidad_entrada_total');
            
            // Agregar campo discrepancia_total
            $table->boolean('discrepancia_total')->default(false)->after('cantidad_entrada_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            // Eliminar campo discrepancia_total
            $table->dropColumn('discrepancia_total');
            
            // Renombrar campos de vuelta
            $table->renameColumn('cantidad_salida_total', 'cantidad_salida');
            $table->renameColumn('cantidad_entrada_total', 'cantidad_entrada');
        });
    }
};
