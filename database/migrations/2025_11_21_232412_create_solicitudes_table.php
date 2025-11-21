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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('hospital_id')->constrained('hospitales')->onDelete('cascade');
            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');
            $table->foreignId('insumo_id')->constrained('insumos')->onDelete('cascade');
            $table->integer('cantidad')->default(0);
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'entregada'])->default('pendiente');
            $table->text('descripcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('fecha_solicitud')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
