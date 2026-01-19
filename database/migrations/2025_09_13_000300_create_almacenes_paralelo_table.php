<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('almacenes_paralelo', function (Blueprint $table) {
            $table->id();
            $table->string('insumos')->nullable();
            $table->string('codigo')->nullable();
            $table->string('numero_lote')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->unsignedInteger('cantidad')->default(0);
            $table->enum('status', ['activo','inactivo'])->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacenes_paralelo');
    }
};
