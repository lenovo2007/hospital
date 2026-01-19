<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            // Eliminar índice UNIQUE de RIF (permitir duplicados y NULL)
            if (Schema::hasColumn('hospitales', 'rif')) {
                try {
                    $table->dropUnique('hospitales_rif_unique');
                } catch (\Exception $e) {
                    // El índice puede no existir en algunas instalaciones
                }
                
                // Hacer el campo RIF nullable
                $table->string('rif', 255)->nullable()->change();
            }
            
            // Agregar índice UNIQUE a cod_sicm (permitir NULL)
            if (Schema::hasColumn('hospitales', 'cod_sicm')) {
                // Primero verificar si ya existe el índice
                $indexes = DB::select("SHOW INDEX FROM hospitales WHERE Column_name = 'cod_sicm'");
                $hasUniqueIndex = false;
                foreach ($indexes as $index) {
                    if ($index->Non_unique == 0) {
                        $hasUniqueIndex = true;
                        break;
                    }
                }
                
                if (!$hasUniqueIndex) {
                    // Crear índice UNIQUE que permite NULL
                    DB::statement('CREATE UNIQUE INDEX hospitales_cod_sicm_unique ON hospitales (cod_sicm)');
                }
            }
            
            // Agregar campo codigo_alt (código alternativo generado automáticamente)
            if (!Schema::hasColumn('hospitales', 'codigo_alt')) {
                $table->string('codigo_alt', 20)->nullable()->unique()->after('cod_sicm');
            }
        });
        
        // Generar codigo_alt para registros existentes que no lo tengan
        $hospitales = DB::table('hospitales')->whereNull('codigo_alt')->orderBy('id')->get();
        
        if ($hospitales->count() > 0) {
            // Obtener el último codigo_alt existente
            $lastCodigo = DB::table('hospitales')
                ->whereNotNull('codigo_alt')
                ->orderByDesc('codigo_alt')
                ->value('codigo_alt');
            
            $nextNumber = 1;
            if ($lastCodigo && preg_match('/HOSP-(\d+)/', $lastCodigo, $matches)) {
                $nextNumber = (int)$matches[1] + 1;
            }
            
            foreach ($hospitales as $hospital) {
                $nuevoCodigo = 'HOSP-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                DB::table('hospitales')
                    ->where('id', $hospital->id)
                    ->update(['codigo_alt' => $nuevoCodigo]);
                $nextNumber++;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            // Restaurar índice UNIQUE de RIF
            if (Schema::hasColumn('hospitales', 'rif')) {
                try {
                    $table->unique('rif', 'hospitales_rif_unique');
                } catch (\Exception $e) {
                    // Puede fallar si hay duplicados
                }
            }
            
            // Eliminar índice UNIQUE de cod_sicm
            if (Schema::hasColumn('hospitales', 'cod_sicm')) {
                try {
                    DB::statement('DROP INDEX hospitales_cod_sicm_unique ON hospitales');
                } catch (\Exception $e) {
                    // El índice puede no existir
                }
            }
            
            // Eliminar campo codigo_alt
            if (Schema::hasColumn('hospitales', 'codigo_alt')) {
                $table->dropColumn('codigo_alt');
            }
        });
    }
};
