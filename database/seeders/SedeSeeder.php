<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Evitar duplicados
        if (Sede::count() > 0) {
            $this->command->info('Ya existen sedes en la base de datos. No se agregarán más.');
            return;
        }

        $sedes = [
            // Sedes para el Hospital Central de Caracas (ID 1)
            [
                'nombre' => 'Sede Principal',
                'tipo_almacen' => 'Almacén Central',
                'hospital_id' => 1,
                'status' => 'activo'
            ],
            [
                'nombre' => 'Sede de Emergencias',
                'tipo_almacen' => 'Almacén de Emergencia',
                'hospital_id' => 1,
                'status' => 'activo'
            ],
            
            // Sedes para el Hospital Universitario de Caracas (ID 2)
            [
                'nombre' => 'Sede Principal UCV',
                'tipo_almacen' => 'Almacén Central',
                'hospital_id' => 2,
                'status' => 'activo'
            ],
            [
                'nombre' => 'Sede de Consultorios Externos',
                'tipo_almacen' => 'Almacén de Consultorios',
                'hospital_id' => 2,
                'status' => 'activo'
            ],
            
            // Sedes para el Hospital de Niños J.M. de los Ríos (ID 3)
            [
                'nombre' => 'Sede Principal Pediátrica',
                'tipo_almacen' => 'Almacén Pediátrico',
                'hospital_id' => 3,
                'status' => 'activo'
            ],
            [
                'nombre' => 'Sede de Emergencias Pediátricas',
                'tipo_almacen' => 'Almacén de Emergencia',
                'hospital_id' => 3,
                'status' => 'activo'
            ]
        ];

        foreach ($sedes as $sede) {
            Sede::create($sede);
        }

        $this->command->info('Sedes de ejemplo creadas exitosamente.');
    }
}
