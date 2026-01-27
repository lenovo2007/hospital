<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_centrales')) {
            Schema::create('almacenes_centrales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('insumo_id')->nullable()->index();
                $table->unsignedInteger('cantidad')->default(0);
                $table->unsignedBigInteger('sede_id')->nullable()->index();
                $table->unsignedBigInteger('lote_id')->nullable()->index();
                $table->unsignedBigInteger('hospital_id')->nullable()->index();
                $table->string('estado', 50)->default('pendiente');
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });

            return;
        }

        Schema::table('almacenes_centrales', function (Blueprint $table) {
            if (!Schema::hasColumn('almacenes_centrales', 'insumo_id')) {
                $table->unsignedBigInteger('insumo_id')->nullable()->after('id');
                $table->index('insumo_id');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('insumo_id');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('cantidad');
                $table->index('sede_id');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('sede_id');
                $table->index('lote_id');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('lote_id');
                $table->index('hospital_id');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'status')) {
                $table->boolean('status')->default(true)->after('hospital_id');
                $table->index('status');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'estado')) {
                $table->string('estado', 50)->default('pendiente')->after('status');
            }

            if (!Schema::hasColumn('almacenes_centrales', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // No se elimina la tabla para evitar pérdida de datos en ambientes ya en uso.
    }
};
