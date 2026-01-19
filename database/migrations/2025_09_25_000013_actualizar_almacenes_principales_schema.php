<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajusta la tabla almacenes_principales al nuevo esquema reducido.
     */
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_principales')) {
            Schema::create('almacenes_principales', function (Blueprint $table) {
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

        Schema::table('almacenes_principales', function (Blueprint $table) {
            foreach (['insumos', 'codigo', 'numero_lote', 'fecha_vencimiento', 'fecha_ingreso', 'nombre'] as $columna) {
                if (Schema::hasColumn('almacenes_principales', $columna)) {
                    $table->dropColumn($columna);
                }
            }

            if (!Schema::hasColumn('almacenes_principales', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('id');
            }
            if (!Schema::hasColumn('almacenes_principales', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('cantidad');
                $table->index('sede_id');
            }
            if (!Schema::hasColumn('almacenes_principales', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('sede_id');
                $table->index('lote_id');
            }
            if (!Schema::hasColumn('almacenes_principales', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('lote_id');
                $table->index('hospital_id');
            }
            if (Schema::hasColumn('almacenes_principales', 'status')) {
                $table->boolean('status')->default(true)->change();
                $table->index('status');
            } else {
                $table->boolean('status')->default(true)->after('hospital_id');
                $table->index('status');
            }
        });
    }

    /**
     * Reversión mínima para mantener compatibilidad.
     */
    public function down(): void
    {
        if (!Schema::hasTable('almacenes_principales')) {
            return;
        }

        Schema::table('almacenes_principales', function (Blueprint $table) {
            // No se recrean columnas eliminadas automáticamente.
        });
    }
};
