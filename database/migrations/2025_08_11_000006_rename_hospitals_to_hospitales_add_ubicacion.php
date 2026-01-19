<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Renombrar tabla
        if (Schema::hasTable('hospitals') && !Schema::hasTable('hospitales')) {
            Schema::rename('hospitals', 'hospitales');
        }

        // Agregar columna JSON ubicacion y eliminar lat/lon
        Schema::table('hospitales', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitales', 'ubicacion')) {
                $table->json('ubicacion')->nullable()->after('rif');
            }
        });

        // Migrar datos existentes de lat/lon a ubicacion si existen
        if (Schema::hasColumn('hospitales', 'lat') && Schema::hasColumn('hospitales', 'lon')) {
            DB::table('hospitales')->select('id', 'lat', 'lon')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('hospitales')->where('id', $row->id)->update([
                        'ubicacion' => json_encode(['lat' => $row->lat, 'lon' => $row->lon]),
                    ]);
                }
            });
        }

        Schema::table('hospitales', function (Blueprint $table) {
            if (Schema::hasColumn('hospitales', 'lat')) {
                $table->dropColumn('lat');
            }
            if (Schema::hasColumn('hospitales', 'lon')) {
                $table->dropColumn('lon');
            }
        });
    }

    public function down(): void
    {
        // Restaurar columnas lat/lon desde ubicacion
        Schema::table('hospitales', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitales', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('rif');
            }
            if (!Schema::hasColumn('hospitales', 'lon')) {
                $table->decimal('lon', 10, 7)->nullable()->after('lat');
            }
        });

        if (Schema::hasColumn('hospitales', 'ubicacion')) {
            DB::table('hospitales')->select('id', 'ubicacion')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $lat = null; $lon = null;
                    if (!is_null($row->ubicacion)) {
                        $arr = json_decode($row->ubicacion, true);
                        if (is_array($arr)) {
                            $lat = $arr['lat'] ?? null;
                            $lon = $arr['lon'] ?? null;
                        }
                    }
                    DB::table('hospitales')->where('id', $row->id)->update([
                        'lat' => $lat,
                        'lon' => $lon,
                    ]);
                }
            });
        }

        Schema::table('hospitales', function (Blueprint $table) {
            if (Schema::hasColumn('hospitales', 'ubicacion')) {
                $table->dropColumn('ubicacion');
            }
        });

        // Renombrar de vuelta la tabla si existía originalmente
        if (Schema::hasTable('hospitales') && !Schema::hasTable('hospitals')) {
            Schema::rename('hospitales', 'hospitals');
        }
    }
};
