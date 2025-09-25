<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_centrales')) {
            // Create minimal table if missing
            Schema::create('almacenes_centrales', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('cantidad')->default(0);
                $table->unsignedBigInteger('sede_id')->nullable()->index();
                $table->unsignedBigInteger('lote_id')->nullable()->index();
                $table->unsignedBigInteger('hospital_id')->nullable()->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });
            return;
        }

        Schema::table('almacenes_centrales', function (Blueprint $table) {
            // Remove old columns if present
            foreach (['insumos','codigo','numero_lote','fecha_vencimiento','fecha_ingreso','nombre'] as $col) {
                if (Schema::hasColumn('almacenes_centrales', $col)) {
                    $table->dropColumn($col);
                }
            }

            // Ensure required columns exist
            if (!Schema::hasColumn('almacenes_centrales', 'cantidad')) {
                $table->unsignedInteger('cantidad')->default(0)->after('id');
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
        });
    }

    public function down(): void
    {
        // Only revert to a minimal safe state (non-destructive)
        if (!Schema::hasTable('almacenes_centrales')) {
            return;
        }
        Schema::table('almacenes_centrales', function (Blueprint $table) {
            // Keep the new schema; you can manually re-add removed columns if needed.
        });
    }
};
