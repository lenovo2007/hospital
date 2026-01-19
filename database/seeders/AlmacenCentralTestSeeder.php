<?php

namespace Database\Seeders;

use App\Models\AlmacenCentral;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Lote;
use App\Models\Sede;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlmacenCentralTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $hospital = Hospital::first() ?? Hospital::create([
                'nombre' => 'Hospital Central de Pruebas',
                'rif' => 'J-00000000-0',
                'direccion' => 'Ciudad Demo',
                'telefono' => '000-0000000',
                'email' => 'demo@hospital.test',
                'tipo' => 'publico',
                'status' => 'activo',
            ]);

            $sede = Sede::firstOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'nombre' => 'Sede Central Demo',
                ],
                [
                    'tipo_almacen' => 'almacen_central',
                    'status' => 'activo',
                ]
            );

            $insumo = Insumo::firstOrCreate(
                ['codigo' => 'INS-DEMO-001'],
                [
                    'nombre' => 'Guantes de látex demo',
                    'tipo' => 'descartable',
                    'unidad_medida' => 'unidad',
                    'cantidad_por_paquete' => 100,
                    'status' => 'activo',
                ]
            );

            $lote = Lote::firstOrCreate(
                [
                    'id_insumo' => $insumo->id,
                    'numero_lote' => 'LOT-DEMO-001',
                    'hospital_id' => $hospital->id,
                ],
                [
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'fecha_ingreso' => now()->toDateString(),
                ]
            );

            AlmacenCentral::updateOrCreate(
                [
                    'sede_id' => $sede->id,
                    'lote_id' => $lote->id,
                    'hospital_id' => $hospital->id,
                ],
                [
                    'cantidad' => 250,
                    'status' => true,
                ]
            );
        });
    }
}
