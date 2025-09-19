<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipos_hospital_distribuciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 100)->unique();
            $table->decimal('porcentaje', 5, 2); // porcentaje en % (0.00 - 100.00)
            $table->timestamps();
        });

        // Seed valores por defecto solicitados
        DB::table('tipos_hospital_distribuciones')->insert([
            ['tipo' => 'Tipo 1', 'porcentaje' => 0.11, 'created_at' => now(), 'updated_at' => now()],
            ['tipo' => 'Tipo 2', 'porcentaje' => 0.21, 'created_at' => now(), 'updated_at' => now()],
            ['tipo' => 'Tipo 3', 'porcentaje' => 0.53, 'created_at' => now(), 'updated_at' => now()],
            ['tipo' => 'Tipo 4', 'porcentaje' => 1.74, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_hospital_distribuciones');
    }
};
