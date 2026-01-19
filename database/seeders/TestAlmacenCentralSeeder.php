<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestAlmacenCentralSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('almacenes_centrales')) {
            $this->command?->warn('Tabla almacenes_centrales no existe, se omite.');
            return;
        }

        $insumoId = DB::table('insumos')->value('id');
        if (!$insumoId) {
            $this->command?->warn('No hay insumos en la tabla insumos; se omite.');
            return;
        }

        DB::table('almacenes_centrales')->insert([
            'insumos' => $insumoId,
            'codigo' => 'TEST-COD',
            'numero_lote' => 'TEST-LOT-001',
            'fecha_vencimiento' => now()->addMonths(6),
            'fecha_ingreso' => now(),
            'cantidad' => 25,
            'status' => true,
            'sede_id' => 1,
            'lote_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('Registro de prueba insertado en almacenes_centrales.');
    }
}
