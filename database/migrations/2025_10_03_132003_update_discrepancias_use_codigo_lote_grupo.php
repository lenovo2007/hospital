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
        Schema::table('movimientos_discrepancias', function (Blueprint $table) {
            // Eliminar la foreign key y columna lote_id
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
            
            // Agregar codigo_lote_grupo
            $table->string('codigo_lote_grupo', 50)->after('movimiento_stock_id');
            
            // Agregar foreign key a lotes_grupos
            $table->foreign('codigo_lote_grupo')->references('codigo')->on('lotes_grupos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_discrepancias', function (Blueprint $table) {
            // Eliminar foreign key y columna codigo_lote_grupo
            $table->dropForeign(['codigo_lote_grupo']);
            $table->dropColumn('codigo_lote_grupo');
            
            // Restaurar lote_id
            $table->unsignedBigInteger('lote_id')->nullable()->after('movimiento_stock_id');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('set null');
        });
    }
};
