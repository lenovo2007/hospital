<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla lote_grupo para agrupar items de movimientos
     */
    public function up(): void
    {
        Schema::create('lote_grupo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // cod001, cod002, etc.
            $table->unsignedBigInteger('lote_id');
            $table->integer('cantidad');
            $table->enum('status', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();

            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('cascade');
            $table->index(['codigo', 'status'], 'lote_grupo_codigo_status_idx');
        });
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        Schema::dropIfExists('lote_grupo');
    }
};
