<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombrar tabla lote_grupo a lotes_grupos
     */
    public function up(): void
    {
        // Verificar si la tabla antigua existe y la nueva no existe
        if (Schema::hasTable('lote_grupo') && !Schema::hasTable('lotes_grupos')) {
            Schema::rename('lote_grupo', 'lotes_grupos');
        }
    }

    /**
     * Revertir el cambio
     */
    public function down(): void
    {
        // Verificar si la tabla nueva existe y la antigua no existe
        if (Schema::hasTable('lotes_grupos') && !Schema::hasTable('lote_grupo')) {
            Schema::rename('lotes_grupos', 'lote_grupo');
        }
    }
};
