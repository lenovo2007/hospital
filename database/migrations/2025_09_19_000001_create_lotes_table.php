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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_insumo');
            $table->string('numero_lote', 100);
            $table->date('fecha_vencimiento');
            $table->date('fecha_ingreso');
            $table->unsignedBigInteger('hospital_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('id_insumo')
                  ->references('id')
                  ->on('insumos')
                  ->onDelete('cascade');

            $table->foreign('hospital_id')
                  ->references('id')
                  ->on('hospitales')
                  ->onDelete('cascade');

            // Indexes & constraints
            $table->index('fecha_vencimiento');
            $table->unique(['id_insumo', 'numero_lote', 'hospital_id'], 'lotes_insumo_lote_hospital_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
