<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renombrar tabla almacenes -> sedes
        if (Schema::hasTable('almacenes') && !Schema::hasTable('sedes')) {
            Schema::rename('almacenes', 'sedes');
        }

        // Agregar columnas tipo y hospital_id
        Schema::table('sedes', function (Blueprint $table) {
            if (!Schema::hasColumn('sedes', 'tipo')) {
                $table->string('tipo')->after('nombre');
            }
            if (!Schema::hasColumn('sedes', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('tipo');
                $table->foreign('hospital_id')->references('id')->on('hospitales')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Revertir columnas
        if (Schema::hasTable('sedes')) {
            Schema::table('sedes', function (Blueprint $table) {
                if (Schema::hasColumn('sedes', 'hospital_id')) {
                    $table->dropForeign(['hospital_id']);
                    $table->dropColumn('hospital_id');
                }
                if (Schema::hasColumn('sedes', 'tipo')) {
                    $table->dropColumn('tipo');
                }
            });
        }

        // Renombrar tabla sedes -> almacenes si existía originalmente
        if (Schema::hasTable('sedes') && !Schema::hasTable('almacenes')) {
            Schema::rename('sedes', 'almacenes');
        }
    }
};
