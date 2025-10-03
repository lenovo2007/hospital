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
            // Renombrar campos solo si existen con el nombre anterior
            if (Schema::hasColumn('movimientos_stock', 'cantidad_salida') && !Schema::hasColumn('movimientos_stock', 'cantidad_salida_total')) {
                $table->renameColumn('cantidad_salida', 'cantidad_salida_total');
            }
            
            if (Schema::hasColumn('movimientos_stock', 'cantidad_entrada') && !Schema::hasColumn('movimientos_stock', 'cantidad_entrada_total')) {
                $table->renameColumn('cantidad_entrada', 'cantidad_entrada_total');
            }
            
            // Agregar campo discrepancia_total solo si no existe
            if (!Schema::hasColumn('movimientos_stock', 'discrepancia_total')) {
                $table->boolean('discrepancia_total')->default(false);
            }
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
