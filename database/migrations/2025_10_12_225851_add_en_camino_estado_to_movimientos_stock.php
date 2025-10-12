<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar 'en_camino' al enum de estado
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'despachado', 'en_camino', 'entregado', 'recibido', 'cancelado') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum anterior (sin en_camino)
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'despachado', 'entregado', 'recibido', 'cancelado') DEFAULT 'pendiente'");
    }
};
