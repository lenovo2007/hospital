<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'hospital_id')) {
                if ($this->indexExists('movimientos_stock', 'mov_stock_tipo_hosp_idx')) {
                    $table->dropIndex('mov_stock_tipo_hosp_idx');
                }
            }
        });

        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'hospital_id')) {
                $table->renameColumn('hospital_id', 'destino_hospital_id');
            }

            if (Schema::hasColumn('movimientos_stock', 'sede_id')) {
                $table->renameColumn('sede_id', 'destino_sede_id');
            }

            if (!Schema::hasColumn('movimientos_stock', 'origen_hospital_id')) {
                $table->unsignedBigInteger('origen_hospital_id')->nullable()->after('destino_almacen_id');
            }

            if (!Schema::hasColumn('movimientos_stock', 'origen_sede_id')) {
                $table->unsignedBigInteger('origen_sede_id')->nullable()->after('origen_hospital_id');
            }

            if (Schema::hasColumn('movimientos_stock', 'destino_hospital_id')) {
                $table->index(['tipo', 'destino_hospital_id'], 'mov_stock_tipo_destino_hosp_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if ($this->indexExists('movimientos_stock', 'mov_stock_tipo_destino_hosp_idx')) {
                $table->dropIndex('mov_stock_tipo_destino_hosp_idx');
            }
        });

        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_stock', 'hospital_id') && Schema::hasColumn('movimientos_stock', 'destino_hospital_id')) {
                $table->renameColumn('destino_hospital_id', 'hospital_id');
            }

            if (!Schema::hasColumn('movimientos_stock', 'sede_id') && Schema::hasColumn('movimientos_stock', 'destino_sede_id')) {
                $table->renameColumn('destino_sede_id', 'sede_id');
            }

            if (Schema::hasColumn('movimientos_stock', 'origen_hospital_id')) {
                $table->dropColumn('origen_hospital_id');
            }

            if (Schema::hasColumn('movimientos_stock', 'origen_sede_id')) {
                $table->dropColumn('origen_sede_id');
            }

            if (Schema::hasColumn('movimientos_stock', 'hospital_id')) {
                $table->index(['tipo', 'hospital_id'], 'mov_stock_tipo_hosp_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(1) AS total
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, $table, $index]
        );

        return (int) ($result->total ?? 0) > 0;
    }
};
