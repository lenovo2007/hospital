<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_stock', 'tipo_movimiento')) {
                $table->string('tipo_movimiento', 50)->nullable()->after('tipo');
            }

            if (!Schema::hasColumn('movimientos_stock', 'fecha_despacho')) {
                $table->dateTime('fecha_despacho')->nullable()->after('cantidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'fecha_despacho')) {
                $table->dropColumn('fecha_despacho');
            }

            if (Schema::hasColumn('movimientos_stock', 'tipo_movimiento')) {
                $table->dropColumn('tipo_movimiento');
            }
        });
    }
};
