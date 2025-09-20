<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitudes_faltantes')) {
            Schema::create('solicitudes_faltantes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hospital_id');
                $table->string('almacen_tipo', 100);
                $table->unsignedBigInteger('almacen_id');
                $table->unsignedBigInteger('insumo_id');
                $table->integer('cantidad_sugerida')->nullable();
                $table->string('prioridad', 20)->default('media'); // baja, media, alta
                $table->string('estado', 20)->default('pendiente'); // pendiente, atendida, cancelada
                $table->unsignedBigInteger('user_id');
                $table->text('comentario')->nullable();
                $table->timestamps();

                $table->foreign('hospital_id')->references('id')->on('hospitales')->onDelete('cascade');
                $table->foreign('insumo_id')->references('id')->on('insumos')->onDelete('cascade');
                $table->index(['hospital_id', 'almacen_tipo', 'almacen_id', 'estado'], 'sol_falt_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_faltantes');
    }
};
