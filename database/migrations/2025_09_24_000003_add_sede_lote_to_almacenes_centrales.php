<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('almacenes_centrales')) {
            return;
        }
        Schema::table('almacenes_centrales', function (Blueprint $table) {
            if (!Schema::hasColumn('almacenes_centrales', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('status');
                $table->index('sede_id');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('sede_id');
                $table->index('lote_id');
            }
            if (!Schema::hasColumn('almacenes_centrales', 'status')) {
                $table->boolean('status')->default(true)->after('cantidad');
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('almacenes_centrales')) {
            return;
        }
        Schema::table('almacenes_centrales', function (Blueprint $table) {
            if (Schema::hasColumn('almacenes_centrales', 'lote_id')) {
                $table->dropIndex(['lote_id']);
                $table->dropColumn('lote_id');
            }
            if (Schema::hasColumn('almacenes_centrales', 'sede_id')) {
                $table->dropIndex(['sede_id']);
                $table->dropColumn('sede_id');
            }
            // No eliminamos status si existía antes; si lo agregamos en up, podríamos quitarlo
            // pero evitar cambios destructivos si había datos.
        });
    }
};
