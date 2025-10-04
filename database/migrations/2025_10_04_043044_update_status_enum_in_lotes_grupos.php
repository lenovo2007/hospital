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
        DB::statement("ALTER TABLE lotes_grupos MODIFY COLUMN status ENUM('activo', 'entregado', 'recibido', 'inactivo') DEFAULT 'activo'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original
        DB::statement("ALTER TABLE lotes_grupos MODIFY COLUMN status ENUM('activo', 'inactivo') DEFAULT 'activo'");
    }
};
