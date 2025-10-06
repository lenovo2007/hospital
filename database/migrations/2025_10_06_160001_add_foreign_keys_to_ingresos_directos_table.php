<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingresos_directos', function (Blueprint $table) {
            // Agregar claves foráneas solo si las tablas existen
            if (Schema::hasTable('hospitales')) {
                $table->foreign('hospital_id')->references('id')->on('hospitales')->onDelete('cascade');
            }
            
            if (Schema::hasTable('sedes')) {
                $table->foreign('sede_id')->references('id')->on('sedes')->onDelete('cascade');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('user_id_procesado')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos_directos', function (Blueprint $table) {
            // Eliminar claves foráneas
            $table->dropForeign(['hospital_id']);
            $table->dropForeign(['sede_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['user_id_procesado']);
        });
    }
};
