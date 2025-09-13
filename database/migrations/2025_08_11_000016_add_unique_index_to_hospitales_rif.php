<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            // Añadir índice único a rif si no existe
            $table->unique('rif', 'hospitales_rif_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->dropUnique('hospitales_rif_unique');
        });
    }
};
