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
        // Primero expandir el enum para incluir todos los valores (viejos y nuevos)
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'en_transito', 'entregado', 'completado', 'inconsistente', 'cancelado', 'despachado', 'recibido') DEFAULT 'pendiente'");
        
        // Actualizar registros existentes para mapear al nuevo flujo
        DB::statement("UPDATE movimientos_stock SET estado = 'despachado' WHERE estado = 'en_transito'");
        DB::statement("UPDATE movimientos_stock SET estado = 'recibido' WHERE estado IN ('completado', 'inconsistente')");
        
        // Finalmente reducir el enum solo a los nuevos valores
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'despachado', 'entregado', 'recibido', 'cancelado') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum anterior
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'en_transito', 'entregado', 'completado', 'inconsistente', 'cancelado') DEFAULT 'pendiente'");
        
        // Revertir los datos
        DB::statement("UPDATE movimientos_stock SET estado = 'en_transito' WHERE estado = 'despachado'");
        DB::statement("UPDATE movimientos_stock SET estado = 'completado' WHERE estado = 'recibido'");
    }
};
