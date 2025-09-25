<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipos_hospital_distribuciones')) {
            Schema::create('tipos_hospital_distribuciones', function (Blueprint $table) {
                $table->id();
                $table->decimal('tipo1', 8, 2)->default(0);
                $table->decimal('tipo2', 8, 2)->default(0);
                $table->decimal('tipo3', 8, 2)->default(0);
                $table->decimal('tipo4', 8, 2)->default(0);
                $table->timestamps();
            });
            return;
        }

        $valoresExistentes = collect();
        if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo') && Schema::hasColumn('tipos_hospital_distribuciones', 'porcentaje')) {
            $valoresExistentes = DB::table('tipos_hospital_distribuciones')->get();
        }

        try {
            DB::statement('ALTER TABLE tipos_hospital_distribuciones DROP CONSTRAINT chk_tipos_hosp_dist_tipo');
        } catch (\Throwable $e) {
        }

        Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo')) {
                $table->dropColumn('tipo');
            }
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'porcentaje')) {
                $table->dropColumn('porcentaje');
            }
        });

        Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
            if (!Schema::hasColumn('tipos_hospital_distribuciones', 'tipo1')) {
                $table->decimal('tipo1', 8, 2)->default(0);
            }
            if (!Schema::hasColumn('tipos_hospital_distribuciones', 'tipo2')) {
                $table->decimal('tipo2', 8, 2)->default(0);
            }
            if (!Schema::hasColumn('tipos_hospital_distribuciones', 'tipo3')) {
                $table->decimal('tipo3', 8, 2)->default(0);
            }
            if (!Schema::hasColumn('tipos_hospital_distribuciones', 'tipo4')) {
                $table->decimal('tipo4', 8, 2)->default(0);
            }
        });

        DB::table('tipos_hospital_distribuciones')->truncate();

        $mapa = [
            'Tipo 1' => 0,
            'Tipo 2' => 0,
            'Tipo 3' => 0,
            'Tipo 4' => 0,
        ];

        foreach ($valoresExistentes as $fila) {
            $tipo = $fila->tipo ?? null;
            if ($tipo && array_key_exists($tipo, $mapa)) {
                $mapa[$tipo] = (float) $fila->porcentaje;
            }
        }

        DB::table('tipos_hospital_distribuciones')->insert([
            'tipo1' => $mapa['Tipo 1'],
            'tipo2' => $mapa['Tipo 2'],
            'tipo3' => $mapa['Tipo 3'],
            'tipo4' => $mapa['Tipo 4'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('tipos_hospital_distribuciones')) {
            return;
        }

        Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
            if (!Schema::hasColumn('tipos_hospital_distribuciones', 'tipo')) {
                $table->string('tipo', 100)->nullable();
            }
            if (!Schema::hasColumn('tipos_hospital_distribuciones', 'porcentaje')) {
                $table->decimal('porcentaje', 8, 2)->default(0);
            }
        });

        $registro = DB::table('tipos_hospital_distribuciones')->first();

        DB::table('tipos_hospital_distribuciones')->truncate();

        if ($registro) {
            $tipos = ['Tipo 1' => $registro->tipo1 ?? 0, 'Tipo 2' => $registro->tipo2 ?? 0, 'Tipo 3' => $registro->tipo3 ?? 0, 'Tipo 4' => $registro->tipo4 ?? 0];
        } else {
            $tipos = ['Tipo 1' => 0, 'Tipo 2' => 0, 'Tipo 3' => 0, 'Tipo 4' => 0];
        }

        foreach ($tipos as $tipo => $porcentaje) {
            DB::table('tipos_hospital_distribuciones')->insert([
                'tipo' => $tipo,
                'porcentaje' => $porcentaje,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('tipos_hospital_distribuciones', function (Blueprint $table) {
            if (Schema::hasColumn('tipos_hospital_distribuciones', 'tipo1')) {
                $table->dropColumn(['tipo1', 'tipo2', 'tipo3', 'tipo4']);
            }
        });
    }
};
