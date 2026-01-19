<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar campo estado a la tabla movimientos_stock
     */
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_stock', 'estado')) {
                $table->enum('estado', ['completado', 'cancelado', 'pendiente'])->default('pendiente')->after('user_id');
            }
        });
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};
