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
        // Modificar el enum para incluir los nuevos estados
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'en_transito', 'entregado', 'completado', 'inconsistente', 'cancelado') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('completado', 'cancelado', 'pendiente') DEFAULT 'pendiente'");
    }
};
