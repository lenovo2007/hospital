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

            // Determinar el nombre de la columna de tipo de almacén en el entorno actual
            $tipoCol = Schema::hasColumn('lotes_almacenes', 'almacen_tipo')
                ? 'almacen_tipo'
                : (Schema::hasColumn('lotes_almacenes', 'tipo_almacen') ? 'tipo_almacen' : null);

            // Actualizar índice único: reemplazar almacen_id por sede_id usando la columna detectada
            if ($tipoCol !== null) {
                // Intentar eliminar índice único antiguo si existe (por nombre o por columnas)
                try {
                    Schema::table('lotes_almacenes', function (Blueprint $table) {
                        // Nombre según migración original
                        $table->dropUnique('lote_almacen_unique');
                    });
                } catch (\Throwable $e) {
                    // Si no existe ese nombre, intentar por definición de columnas más común
                    try {
                        Schema::table('lotes_almacenes', function (Blueprint $table) use ($tipoCol) {
                            $table->dropUnique(['lote_id', $tipoCol, 'almacen_id']);
                        });
                    } catch (\Throwable $e2) {
                        // Ignorar si tampoco existe
                    }
                }

                // Crear (o recrear) el índice único con sede_id
                Schema::table('lotes_almacenes', function (Blueprint $table) use ($tipoCol) {
                    $table->unique(['lote_id', $tipoCol, 'sede_id'], 'lote_almacenes_lote_tipo_sede_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lotes_almacenes')) {
            // Revertir índice único a usar almacen_id (intentando con el nombre creado en up)
            try {
                Schema::table('lotes_almacenes', function (Blueprint $table) {
                    $table->dropUnique('lote_almacenes_lote_tipo_sede_unique');
                });
            } catch (\Throwable $e) {
                // Silenciar si no existe
            }

            $tipoCol = Schema::hasColumn('lotes_almacenes', 'almacen_tipo')
                ? 'almacen_tipo'
                : (Schema::hasColumn('lotes_almacenes', 'tipo_almacen') ? 'tipo_almacen' : null);

            if ($tipoCol !== null) {
                Schema::table('lotes_almacenes', function (Blueprint $table) use ($tipoCol) {
                    $table->unique(['lote_id', $tipoCol, 'almacen_id'], 'lote_almacen_unique');
                });
            }

            // Quitar columna sede_id
            Schema::table('lotes_almacenes', function (Blueprint $table) {
                if (Schema::hasColumn('lotes_almacenes', 'sede_id')) {
                    $table->dropColumn('sede_id');
                }
            });
        }
    }
};
