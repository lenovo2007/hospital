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
        Schema::create('ingresos_directos', function (Blueprint $table) {
            $table->id();
            
            // Información básica del ingreso
            $table->string('codigo_ingreso')->unique(); // ING-20251006-001
            $table->enum('tipo_ingreso', ['donacion', 'compra', 'ajuste_inventario', 'devolucion', 'otro']);
            $table->date('fecha_ingreso');
            
            // Sede destino
            $table->unsignedBigInteger('hospital_id');
            $table->unsignedBigInteger('sede_id');
            $table->string('almacen_tipo'); // almacenCent, almacenPrin, etc.
            
            // Información del proveedor/origen
            $table->string('proveedor_nombre')->nullable();
            $table->string('proveedor_rif')->nullable();
            $table->string('numero_factura')->nullable();
            $table->decimal('valor_total', 12, 2)->nullable();
            
            // Detalles del ingreso
            $table->text('observaciones')->nullable();
            $table->text('motivo')->nullable(); // Descripción del motivo del ingreso
            $table->integer('cantidad_total_items');
            
            // Estados del ingreso
            $table->enum('estado', ['registrado', 'procesado', 'cancelado'])->default('registrado');
            
            // Código del grupo de lotes asociado
            $table->string('codigo_lotes_grupo'); // Relaciona con lotes_grupos
            
            // Usuario que registra
            $table->unsignedBigInteger('user_id');
            $table->timestamp('fecha_procesado')->nullable();
            $table->unsignedBigInteger('user_id_procesado')->nullable();
            
            // Control
            $table->boolean('status')->default(true);
            $table->timestamps();
            
            // Índices
            $table->index(['sede_id', 'fecha_ingreso']);
            $table->index(['hospital_id', 'fecha_ingreso']);
            $table->index(['tipo_ingreso', 'estado']);
            $table->index('codigo_lotes_grupo');
            
            // Claves foráneas
            $table->foreign('hospital_id')->references('id')->on('hospitales');
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('user_id_procesado')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingresos_directos');
    }
};
