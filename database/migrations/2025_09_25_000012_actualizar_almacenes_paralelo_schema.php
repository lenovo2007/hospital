<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajusta la tabla almacenes_paralelo al nuevo esquema unificado.
     */
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_paralelo')) {
            Schema::create('almacenes_paralelo', function (Blueprint $table) {
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

        Schema::table('almacenes_paralelo', function (Blueprint $table) {
            foreach (['insumos', 'codigo', 'numero_lote', 'fecha_vencimiento', 'fecha_ingreso'] as $columna) {
                if (Schema::hasColumn('almacenes_paralelo', $columna)) {
                    $table->dropColumn($columna);
                }
            }

            if (!Schema::hasColumn('almacenes_paralelo', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('id');
            }
            if (!Schema::hasColumn('almacenes_paralelo', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('cantidad');
                $table->index('sede_id');
            }
            if (!Schema::hasColumn('almacenes_paralelo', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('sede_id');
                $table->index('lote_id');
            }
            if (!Schema::hasColumn('almacenes_paralelo', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('lote_id');
                $table->index('hospital_id');
            }
            if (Schema::hasColumn('almacenes_paralelo', 'status')) {
                $table->boolean('status')->default(true)->change();
                $table->index('status');
            } else {
                $table->boolean('status')->default(true)->after('hospital_id');
                $table->index('status');
            }
        });
    }

    /**
     * Reversión segura (sin volver a agregar columnas anteriores).
     */
    public function down(): void
    {
        if (!Schema::hasTable('almacenes_paralelo')) {
            return;
        }

        Schema::table('almacenes_paralelo', function (Blueprint $table) {
            // No se restauran las columnas eliminadas automáticamente.
        });
    }
};
