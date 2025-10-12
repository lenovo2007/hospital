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
        // Verificar si la columna ya existe antes de agregarla
        if (!Schema::hasColumn('seguimientos', 'despachador_id')) {
            Schema::table('seguimientos', function (Blueprint $table) {
                $table->unsignedBigInteger('despachador_id')->nullable()->after('user_id_repartidor');
            });
        }
        
        // Nota: Omitimos la clave foránea por ahora debido a incompatibilidades de tipo
        // La integridad referencial se manejará a nivel de aplicación
        // TODO: Investigar tipos de columna en tabla users para agregar FK posteriormente
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            // Solo eliminar la columna si existe (no hay FK que eliminar)
            if (Schema::hasColumn('seguimientos', 'despachador_id')) {
                $table->dropColumn('despachador_id');
            }
        });
    }
};
