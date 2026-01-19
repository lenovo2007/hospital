<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Recrear tabla almacenes_centrales con estructura completa
     */
    public function up(): void
    {
        // Si la tabla existe, no hacer nada (ya está creada)
        if (Schema::hasTable('almacenes_centrales')) {
            return;
        }

        // Crear tabla almacenes_centrales con estructura completa
        Schema::create('almacenes_centrales', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cantidad')->default(0);
            $table->unsignedBigInteger('sede_id')->nullable()->index();
            $table->unsignedBigInteger('lote_id')->nullable()->index();
            $table->unsignedBigInteger('hospital_id')->nullable()->index();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('almacenes_centrales');
    }
};
