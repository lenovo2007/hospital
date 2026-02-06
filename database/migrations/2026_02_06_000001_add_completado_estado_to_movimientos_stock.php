<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $result = DB::select("SHOW COLUMNS FROM movimientos_stock WHERE Field = 'estado'");

        if (empty($result)) {
            return;
        }

        $enumValues = (string) ($result[0]->Type ?? '');

        if ($enumValues === '') {
            return;
        }

        if (strpos($enumValues, 'completado') !== false) {
            return;
        }

        // Agregar 'completado' sin eliminar valores existentes.
        // Nota: mantenemos el orden común; MySQL requiere redefinir el enum completo.
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente','despachado','en_camino','entregado','recibido','completado','cancelado') DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        $result = DB::select("SHOW COLUMNS FROM movimientos_stock WHERE Field = 'estado'");

        if (empty($result)) {
            return;
        }

        $enumValues = (string) ($result[0]->Type ?? '');

        if ($enumValues === '' || strpos($enumValues, 'completado') === false) {
            return;
        }

        // Volver al enum sin 'completado'.
        DB::statement("ALTER TABLE movimientos_stock MODIFY COLUMN estado ENUM('pendiente','despachado','en_camino','entregado','recibido','cancelado') DEFAULT 'pendiente'");
    }
};
