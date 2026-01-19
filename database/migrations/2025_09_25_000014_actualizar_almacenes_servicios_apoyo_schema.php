<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_servicios_apoyo')) {
            Schema::create('almacenes_servicios_apoyo', function (Blueprint $table) {
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

        Schema::table('almacenes_servicios_apoyo', function (Blueprint $table) {
            foreach (['insumos', 'codigo', 'numero_lote', 'fecha_vencimiento', 'fecha_ingreso'] as $columna) {
                if (Schema::hasColumn('almacenes_servicios_apoyo', $columna)) {
                    $table->dropColumn($columna);
                }
            }

            if (!Schema::hasColumn('almacenes_servicios_apoyo', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('id');
            }
            if (!Schema::hasColumn('almacenes_servicios_apoyo', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('cantidad');
                $table->index('sede_id');
            }
            if (!Schema::hasColumn('almacenes_servicios_apoyo', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('sede_id');
                $table->index('lote_id');
            }
            if (!Schema::hasColumn('almacenes_servicios_apoyo', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('lote_id');
                $table->index('hospital_id');
            }
            if (Schema::hasColumn('almacenes_servicios_apoyo', 'status')) {
                $table->boolean('status')->default(true)->change();
                $table->index('status');
            } else {
                $table->boolean('status')->default(true)->after('hospital_id');
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('almacenes_servicios_apoyo')) {
            return;
        }

        Schema::table('almacenes_servicios_apoyo', function (Blueprint $table) {
            // No se reponen las columnas eliminadas automáticamente.
        });
    }
};
