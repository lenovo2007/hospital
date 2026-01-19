<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('almacenes_centrales', function (Blueprint $table) {
            if (Schema::hasColumn('almacenes_centrales', 'nombre')) {
                $table->dropColumn('nombre');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'insumos')) {
                $table->string('insumos')->nullable()->after('id');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'codigo')) {
                $table->string('codigo')->nullable()->after('insumos');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'numero_lote')) {
                $table->string('numero_lote')->nullable()->after('codigo');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'fecha_vencimiento')) {
                $table->date('fecha_vencimiento')->nullable()->after('numero_lote');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'fecha_ingreso')) {
                $table->date('fecha_ingreso')->nullable()->after('fecha_vencimiento');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('fecha_ingreso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('almacenes_centrales', function (Blueprint $table) {
            if (!Schema::hasColumn('almacenes_centrales', 'nombre')) {
                $table->string('nombre')->nullable()->after('id');
            }
            if (Schema::hasColumn('almacenes_centrales', 'insumos')) {
                $table->dropColumn('insumos');
            }
            if (Schema::hasColumn('almacenes_centrales', 'codigo')) {
                $table->dropColumn('codigo');
            }
            if (Schema::hasColumn('almacenes_centrales', 'numero_lote')) {
                $table->dropColumn('numero_lote');
            }
            if (Schema::hasColumn('almacenes_centrales', 'fecha_vencimiento')) {
                $table->dropColumn('fecha_vencimiento');
            }
            if (Schema::hasColumn('almacenes_centrales', 'fecha_ingreso')) {
                $table->dropColumn('fecha_ingreso');
            }
            if (Schema::hasColumn('almacenes_centrales', 'cantidad')) {
                $table->dropColumn('cantidad');
            }
        });
    }
};
