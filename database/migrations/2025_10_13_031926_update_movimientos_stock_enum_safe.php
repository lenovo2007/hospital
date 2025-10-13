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
        // Verificar la estructura actual del enum
        $result = DB::select("SHOW COLUMNS FROM movimientos_stock WHERE Field = 'estado'");
        
        if (!empty($result)) {
            $enumValues = $result[0]->Type;
            
            // Si 'en_camino' no está en el enum, agregarlo
            if (strpos($enumValues, 'en_camino') === false) {
                DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'despachado', 'en_camino', 'entregado', 'recibido', 'cancelado') DEFAULT 'pendiente'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum anterior (sin en_camino) solo si existe
        $result = DB::select("SHOW COLUMNS FROM movimientos_stock WHERE Field = 'estado'");
        
        if (!empty($result)) {
            $enumValues = $result[0]->Type;
            
            // Si 'en_camino' está en el enum, quitarlo
            if (strpos($enumValues, 'en_camino') !== false) {
                DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente', 'despachado', 'entregado', 'recibido', 'cancelado') DEFAULT 'pendiente'");
            }
        }
    }
};
