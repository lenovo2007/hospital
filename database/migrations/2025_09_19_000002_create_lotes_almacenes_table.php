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
        Schema::create('lotes_almacenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lote_id');
            $table->unsignedBigInteger('almacen_id');
            $table->integer('cantidad')->default(0);
            $table->timestamp('ultima_actualizacion')->useCurrent();
            $table->unsignedBigInteger('hospital_id');
            $table->timestamps();

            // Relaciones
            $table->foreign('lote_id')
                  ->references('id')
                  ->on('lotes')
                  ->onDelete('cascade');

            $table->foreign('almacen_id')
                  ->references('id')
                  ->on('almacenes')
                  ->onDelete('cascade');

            $table->foreign('hospital_id')
                  ->references('id')
                  ->on('hospitales')
                  ->onDelete('cascade');

            // Restricción única para no duplicar el mismo lote en el mismo almacén
            $table->unique(['lote_id', 'almacen_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes_almacenes');
    }
};
