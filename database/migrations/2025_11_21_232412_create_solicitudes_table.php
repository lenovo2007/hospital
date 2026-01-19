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
        // Tabla ya existe en producción, solo se crea si no existe
        if (!Schema::hasTable('solicitudes')) {
            Schema::create('solicitudes', function (Blueprint $table) {
                $table->id();
                $table->enum('tipo_solicitud', ['insumo', 'servicio', 'mantenimiento', 'otro']);
                $table->text('descripcion');
                $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media');
                $table->date('fecha');
                $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');
                $table->foreignId('hospital_id')->constrained('hospitales')->onDelete('cascade');
                $table->enum('status', ['pendiente', 'en_proceso', 'completada', 'cancelada'])->default('pendiente');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
