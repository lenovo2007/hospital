<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Esta migración omite las claves foráneas para evitar problemas de compatibilidad
     */
    public function up(): void
    {
        // No hacer nada - la tabla ya existe y funciona sin foreign keys
        // Las relaciones se manejan a nivel de aplicación en los modelos
        
        // Opcional: Agregar comentario a la tabla
        DB::statement("ALTER TABLE ingresos_directos COMMENT = 'Tabla de ingresos directos - relaciones manejadas por aplicación'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hacer nada
    }
};
