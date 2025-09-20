<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('movimientos_stock')) {
            Schema::create('movimientos_stock', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 20); // entrada, salida, transferencia
                $table->unsignedBigInteger('lote_id');
                $table->unsignedBigInteger('hospital_id');
                $table->string('origen_almacen_tipo', 100)->nullable();
                $table->unsignedBigInteger('origen_almacen_id')->nullable();
                $table->string('destino_almacen_tipo', 100)->nullable();
                $table->unsignedBigInteger('destino_almacen_id')->nullable();
                $table->integer('cantidad');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('cascade');
                $table->foreign('hospital_id')->references('id')->on('hospitales')->onDelete('cascade');
                $table->index(['tipo', 'hospital_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
