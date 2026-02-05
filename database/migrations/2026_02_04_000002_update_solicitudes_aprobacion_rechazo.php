<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitudes')) {
            return;
        }

        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'observacion_rechazo')) {
                $table->text('observacion_rechazo')->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('solicitudes', 'status')) {
            try {
                $driver = DB::getDriverName();
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE solicitudes MODIFY COLUMN status ENUM('pendiente','en_proceso','completada','cancelada','aprobado','rechazado') NOT NULL DEFAULT 'pendiente'");
                }
            } catch (\Throwable $e) {
                // Si el motor no soporta ALTER ENUM o falla, no bloquear migración.
            }
        }
    }

    public function down(): void
    {
        // No revertir ENUM para evitar problemas en ambientes con datos.
        if (!Schema::hasTable('solicitudes')) {
            return;
        }

        Schema::table('solicitudes', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes', 'observacion_rechazo')) {
                $table->dropColumn('observacion_rechazo');
            }
        });
    }
};
