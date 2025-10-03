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
        if (!Schema::hasTable('seguimientos')) {
            Schema::create('seguimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movimiento_stock_id');
                $table->json('ubicacion')->nullable(); // {lat, lng, direccion}
                $table->enum('estado', ['despachado', 'en_camino', 'entregado']); 
                $table->enum('status', ['activo', 'completado'])->default('activo');
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('user_id_repartidor');
                $table->timestamps();

                $table->foreign('movimiento_stock_id')->references('id')->on('movimientos_stock')->onDelete('cascade');
                $table->foreign('user_id_repartidor')->references('id')->on('users')->onDelete('cascade');
                
                $table->index(['movimiento_stock_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimientos');
    }
};
