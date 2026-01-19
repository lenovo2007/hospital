<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lotes_almacenes')) {
            return;
        }

        // Si NO existen ninguna de las dos columnas, creamos 'tipo_almacen'
        $hasAlmacenTipo = Schema::hasColumn('lotes_almacenes', 'almacen_tipo');
        $hasTipoAlmacen = Schema::hasColumn('lotes_almacenes', 'tipo_almacen');

        if (!$hasAlmacenTipo && !$hasTipoAlmacen) {
            Schema::table('lotes_almacenes', function (Blueprint $table) {
                $table->string('tipo_almacen', 100)->after('lote_id');
            });
        }

        // Asegurar que existe la columna sede_id (por compatibilidad)
        if (!Schema::hasColumn('lotes_almacenes', 'sede_id')) {
            Schema::table('lotes_almacenes', function (Blueprint $table) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('almacen_id');
            });
        }

        // Preparar el índice único sobre (lote_id, <tipo_col>, sede_id)
        $tipoCol = Schema::hasColumn('lotes_almacenes', 'almacen_tipo')
            ? 'almacen_tipo'
            : (Schema::hasColumn('lotes_almacenes', 'tipo_almacen') ? 'tipo_almacen' : null);

        if ($tipoCol !== null) {
            // Intentar eliminar índices únicos previos con distintos nombres/definiciones
            try {
                Schema::table('lotes_almacenes', function (Blueprint $table) {
                    $table->dropUnique('lote_almacen_unique');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('lotes_almacenes', function (Blueprint $table) use ($tipoCol) {
                    $table->dropUnique(['lote_id', $tipoCol, 'almacen_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('lotes_almacenes', function (Blueprint $table) use ($tipoCol) {
                    $table->dropUnique(['lote_id', $tipoCol, 'sede_id']);
                });
            } catch (\Throwable $e) {}

            // Crear el índice único deseado (si no existe)
            $indexName = 'lote_almacenes_lote_tipo_sede_unique';
            $indexes = DB::select("SHOW INDEXES FROM lotes_almacenes WHERE Key_name = ?", [$indexName]);
            
            if (empty($indexes)) {
                Schema::table('lotes_almacenes', function (Blueprint $table) use ($tipoCol) {
                    $table->unique(['lote_id', $tipoCol, 'sede_id'], 'lote_almacenes_lote_tipo_sede_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('lotes_almacenes')) {
            return;
        }

        // Quitar índice creado
        try {
            Schema::table('lotes_almacenes', function (Blueprint $table) {
                $table->dropUnique('lote_almacenes_lote_tipo_sede_unique');
            });
        } catch (\Throwable $e) {}

        // No se elimina la columna tipo_almacen para evitar pérdida de datos si fue utilizada; si es estrictamente necesario, descomentar:
        // Schema::table('lotes_almacenes', function (Blueprint $table) {
        //     if (Schema::hasColumn('lotes_almacenes', 'tipo_almacen')) {
        //         $table->dropColumn('tipo_almacen');
        //     }
        // });
    }
};
