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
        // Verificar si la tabla ya existe antes de crearla
        if (Schema::hasTable('despachos_pacientes')) {
            return; // La tabla ya existe, no hacer nada
        }

        Schema::create('despachos_pacientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hospital_id');
            $table->unsignedBigInteger('sede_id');
            $table->string('almacen_tipo', 50)->comment('Tipo de almacén desde donde se despacha');
            $table->date('fecha_despacho');
            $table->text('observaciones')->nullable();
            
            // Datos del paciente
            $table->string('paciente_nombres', 100);
            $table->string('paciente_apellidos', 100);
            $table->string('paciente_cedula', 20);
            $table->string('paciente_telefono', 20)->nullable();
            $table->text('paciente_direccion')->nullable();
            
            // Datos médicos opcionales
            $table->string('medico_tratante', 150)->nullable();
            $table->string('diagnostico', 200)->nullable();
            $table->text('indicaciones_medicas')->nullable();
            
            // Control del despacho
            $table->string('codigo_despacho', 50)->unique();
            $table->integer('cantidad_total_items');
            $table->decimal('valor_total', 12, 2)->nullable()->comment('Valor total del despacho');
            $table->enum('estado', ['pendiente', 'despachado', 'entregado', 'cancelado'])->default('despachado');
            $table->timestamp('fecha_entrega')->nullable();
            
            // Usuario que realizó el despacho
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_id_entrega')->nullable()->comment('Usuario que confirma entrega');
            
            // Auditoría
            $table->boolean('status')->default(true);
            $table->timestamps();
            
            // Índices
            $table->index(['hospital_id', 'sede_id']);
            $table->index('paciente_cedula');
            $table->index('fecha_despacho');
            $table->index('estado');
            $table->index('codigo_despacho');
            
            // Foreign keys (commented out to avoid constraint errors)
            // $table->foreign('hospital_id')->references('id')->on('hospitales');
            // $table->foreign('sede_id')->references('id')->on('sedes');
            // $table->foreign('user_id')->references('id')->on('users');
            // $table->foreign('user_id_entrega')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('despachos_pacientes');
    }
};
