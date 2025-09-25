<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración que ajusta la tabla almacenes_farmacia al nuevo esquema requerido.
     */
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_farmacia')) {
            Schema::create('almacenes_farmacia', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('cantidad')->default(0);
                $table->unsignedBigInteger('sede_id')->nullable()->index();
                $table->unsignedBigInteger('lote_id')->nullable()->index();
                $table->unsignedBigInteger('hospital_id')->nullable()->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });
            return;
        }

        Schema::table('almacenes_farmacia', function (Blueprint $table) {
            foreach (['insumos', 'codigo', 'numero_lote', 'fecha_vencimiento', 'fecha_ingreso'] as $columna) {
                if (Schema::hasColumn('almacenes_farmacia', $columna)) {
                    $table->dropColumn($columna);
                }
            }

            if (!Schema::hasColumn('almacenes_farmacia', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('id');
            }
            if (!Schema::hasColumn('almacenes_farmacia', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('cantidad');
                $table->index('sede_id');
            }
            if (!Schema::hasColumn('almacenes_farmacia', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('sede_id');
                $table->index('lote_id');
            }
            if (!Schema::hasColumn('almacenes_farmacia', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('lote_id');
                $table->index('hospital_id');
            }
            if (Schema::hasColumn('almacenes_farmacia', 'status')) {
                $table->boolean('status')->default(true)->change();
                $table->index('status');
            } else {
                $table->boolean('status')->default(true)->after('hospital_id');
                $table->index('status');
            }
        });
    }

    /**
     * Reversión mínima (no destructiva) para mantener integridad.
     */
    public function down(): void
    {
        if (!Schema::hasTable('almacenes_farmacia')) {
            return;
        }
        Schema::table('almacenes_farmacia', function (Blueprint $table) {
            // No se restauran columnas eliminadas automáticamente.
        });
    }
};
