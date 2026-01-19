<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eliminar columna lote_id de movimientos_stock
     */
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'lote_id')) {
                $table->dropForeign(['lote_id']);
                $table->dropColumn('lote_id');
            }
        });
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->unsignedBigInteger('lote_id')->after('tipo_movimiento');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('cascade');
        });
    }
};
