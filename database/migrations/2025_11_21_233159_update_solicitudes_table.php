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
        // Solo ejecutar si la tabla existe y tiene las columnas antiguas
        if (Schema::hasTable('solicitudes') && Schema::hasColumn('solicitudes', 'codigo')) {
            Schema::table('solicitudes', function (Blueprint $table) {
                // Eliminar columnas que no se necesitan
                $table->dropColumn(['codigo', 'insumo_id', 'cantidad', 'estado', 'observaciones', 'user_id', 'fecha_solicitud', 'fecha_aprobacion', 'fecha_entrega']);
            });

            Schema::table('solicitudes', function (Blueprint $table) {
                // Agregar nuevas columnas
                $table->enum('tipo_solicitud', ['insumo', 'servicio', 'mantenimiento', 'otro'])->after('id');
                $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media')->after('descripcion');
                $table->date('fecha')->after('prioridad');
                $table->enum('status', ['pendiente', 'en_proceso', 'completada', 'cancelada'])->default('pendiente')->after('fecha');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn(['tipo_solicitud', 'prioridad', 'fecha', 'status']);
        });

        Schema::table('solicitudes', function (Blueprint $table) {
            $table->string('codigo')->unique();
            $table->foreignId('insumo_id')->constrained('insumos')->onDelete('cascade');
            $table->integer('cantidad')->default(0);
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'entregada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('fecha_solicitud')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
        });
    }
};
