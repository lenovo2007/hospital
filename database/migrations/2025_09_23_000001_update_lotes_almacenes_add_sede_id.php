<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lotes_almacenes')) {
            Schema::table('lotes_almacenes', function (Blueprint $table) {
                if (!Schema::hasColumn('lotes_almacenes', 'sede_id')) {
                    $table->unsignedBigInteger('sede_id')->nullable()->after('almacen_id');
                }
            });

            // Copiar datos existentes: sede_id = almacen_id cuando sede_id esté null
            DB::table('lotes_almacenes')
                ->whereNull('sede_id')
                ->update(['sede_id' => DB::raw('almacen_id')]);

            // Actualizar índice único: reemplazar almacen_id por sede_id
            // El índice original se llama 'lote_almacen_unique' según migración anterior
            try {
                Schema::table('lotes_almacenes', function (Blueprint $table) {
                    $table->dropUnique('lote_almacen_unique');
                });
            } catch (\Throwable $e) {
                // Silenciar si no existe el índice (según entorno)
            }

            Schema::table('lotes_almacenes', function (Blueprint $table) {
                $table->unique(['lote_id', 'almacen_tipo', 'sede_id'], 'lote_almacen_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lotes_almacenes')) {
            // Revertir índice único a usar almacen_id
            try {
                Schema::table('lotes_almacenes', function (Blueprint $table) {
                    $table->dropUnique('lote_almacen_unique');
                });
            } catch (\Throwable $e) {
                // Silenciar si no existe
            }

            Schema::table('lotes_almacenes', function (Blueprint $table) {
                $table->unique(['lote_id', 'almacen_tipo', 'almacen_id'], 'lote_almacen_unique');
            });

            // Quitar columna sede_id
            Schema::table('lotes_almacenes', function (Blueprint $table) {
                if (Schema::hasColumn('lotes_almacenes', 'sede_id')) {
                    $table->dropColumn('sede_id');
                }
            });
        }
    }
};
