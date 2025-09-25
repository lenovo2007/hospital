<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tipos = ['Tipo 1', 'Tipo 2', 'Tipo 3', 'Tipo 4'];

    public function up(): void
    {
        // Asegurar estructura básica
        if (!Schema::hasTable('tipos_hospital_distribuciones')) {
            Schema::create('tipos_hospital_distribuciones', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 100); // enum lógica via CHECK
                $table->decimal('porcentaje', 8, 2)->default(0);
                $table->timestamps();
                $table->unique('tipo');
            });
        } else {
            Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
                // Asegurar NOT NULL y UNIQUE
                if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo')) {
                    $table->string('tipo', 100)->nullable(false)->change();
                    $table->unique('tipo', 'tipos_hosp_dist_tipo_unique');
                }
                if (Schema::hasColumn('tipos_hospital_distribuciones', 'porcentaje')) {
                    $table->decimal('porcentaje', 8, 2)->default(0)->change();
                }
            });
        }

        // Agregar CHECK constraint para restringir a los 4 tipos (si el motor lo soporta)
        try {
            $lista = "('" . implode("','", $this->tipos) . "')";
            DB::statement("ALTER TABLE tipos_hospital_distribuciones \
                ADD CONSTRAINT chk_tipos_hosp_dist_tipo \
                CHECK (tipo IN $lista)");
        } catch (\Throwable $e) {
            // Si el motor no soporta o ya existe, continuar sin fallar
        }

        // Normalizar datos existentes: eliminar registros con tipos no válidos
        DB::table('tipos_hospital_distribuciones')
            ->whereNotIn('tipo', $this->tipos)
            ->delete();

        // Insertar los faltantes con porcentaje 0
        foreach ($this->tipos as $tipo) {
            DB::table('tipos_hospital_distribuciones')->updateOrInsert(
                ['tipo' => $tipo],
                ['porcentaje' => DB::raw('COALESCE(porcentaje, 0)')]
            );
        }
    }

    public function down(): void
    {
        // Quitar la restricción CHECK si existe
        try {
            DB::statement('ALTER TABLE tipos_hospital_distribuciones DROP CONSTRAINT chk_tipos_hosp_dist_tipo');
        } catch (\Throwable $e) {
            // Ignorar si no existe o el motor no lo soporta
        }
        // Mantener la tabla y datos; sólo se elimina el constraint. Si deseas, puedes quitar el índice único:
        try {
            Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
                $table->dropUnique('tipos_hosp_dist_tipo_unique');
            });
        } catch (\Throwable $e) {
        }
    }
};
