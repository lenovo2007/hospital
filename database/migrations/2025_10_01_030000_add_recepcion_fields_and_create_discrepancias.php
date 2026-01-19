<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_stock', 'fecha_recepcion')) {
                $table->dateTime('fecha_recepcion')->nullable()->after('fecha_despacho');
            }

            if (!Schema::hasColumn('movimientos_stock', 'observaciones_recepcion')) {
                $table->string('observaciones_recepcion', 500)->nullable()->after('fecha_recepcion');
            }

            if (!Schema::hasColumn('movimientos_stock', 'user_id_receptor')) {
                $table->unsignedBigInteger('user_id_receptor')->nullable()->after('user_id');
            }
        });

        if (!Schema::hasTable('movimientos_discrepancias')) {
            Schema::create('movimientos_discrepancias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movimiento_stock_id');
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->integer('cantidad_esperada')->default(0);
                $table->integer('cantidad_recibida')->default(0);
                $table->string('observaciones', 500)->nullable();
                $table->timestamps();

                $table->foreign('movimiento_stock_id')->references('id')->on('movimientos_stock')->onDelete('cascade');
                $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('movimientos_discrepancias')) {
            Schema::dropIfExists('movimientos_discrepancias');
        }

        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'user_id_receptor')) {
                $table->dropColumn('user_id_receptor');
            }

            if (Schema::hasColumn('movimientos_stock', 'observaciones_recepcion')) {
                $table->dropColumn('observaciones_recepcion');
            }

            if (Schema::hasColumn('movimientos_stock', 'fecha_recepcion')) {
                $table->dropColumn('fecha_recepcion');
            }
        });
    }
};
