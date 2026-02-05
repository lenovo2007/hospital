<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipos_hospital_distribuciones')) {
            Schema::create('tipos_hospital_distribuciones', function (Blueprint $table) {
                $table->id();
                $table->decimal('tipo1', 20, 18)->default(0);
                $table->decimal('tipo2', 20, 18)->default(0);
                $table->decimal('tipo3', 20, 18)->default(0);
                $table->decimal('tipo4', 20, 18)->default(0);
                $table->timestamps();
            });
            return;
        }

        Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo1')) {
                $table->decimal('tipo1', 20, 18)->default(0)->change();
            }
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo2')) {
                $table->decimal('tipo2', 20, 18)->default(0)->change();
            }
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo3')) {
                $table->decimal('tipo3', 20, 18)->default(0)->change();
            }
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo4')) {
                $table->decimal('tipo4', 20, 18)->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        // No se revierte la precisión para evitar pérdida de decimales.
    }
};
