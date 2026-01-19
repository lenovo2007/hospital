<?php

namespace Database\Seeders;

use App\Models\Hospital;
use Illuminate\Database\Seeder;

class HospitalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Evitar duplicados
        if (Hospital::count() > 0) {
            $this->command->info('Ya existen hospitales en la base de datos. No se agregarán más.');
            return;
        }

        $hospitals = [
            [
                'nombre' => 'Hospital Central de Caracas',
                'nombre_completo' => 'Hospital Central de Caracas Dr. Carlos Arvelo',
                'rif' => 'J-00011122-3',
                'email' => 'hcentral@salud.gob.ve',
                'email_contacto' => 'contacto@hcentral.gob.ve',
                'telefono' => '0212-4815555',
                'nombre_contacto' => 'Dra. María Rodríguez',
                'direccion' => 'Av. San Martín, Parroquia San Juan, Caracas',
                'dependencia' => 'MPPS',
                'estado' => 'Distrito Capital',
                'municipio' => 'Libertador',
                'parroquia' => 'San Juan',
                'tipo' => 'Tipo IV',
                'status' => 'activo',
                'ubicacion' => [
                    'lat' => 10.5039,
                    'lng' => -66.9142
                ]
            ],
            [
                'nombre' => 'Hospital Universitario de Caracas',
                'nombre_completo' => 'Hospital Universitario de Caracas',
                'rif' => 'J-00011123-4',
                'email' => 'huc@ucv.ve',
                'email_contacto' => 'informacion@huc.ucv.ve',
                'telefono' => '0212-6053111',
                'nombre_contacto' => 'Dr. Luis Pérez',
                'direccion' => 'Ciudad Universitaria, Parroquia San Pedro, Caracas',
                'dependencia' => 'UCV',
                'estado' => 'Distrito Capital',
                'municipio' => 'Libertador',
                'parroquia' => 'San Pedro',
                'tipo' => 'Tipo IV',
                'status' => 'activo',
                'ubicacion' => [
                    'lat' => 10.4907,
                    'lng' => -66.8919
                ]
            ],
            [
                'nombre' => 'Hospital de Niños J.M. de los Ríos',
                'nombre_completo' => 'Hospital de Niños J.M. de los Ríos',
                'rif' => 'J-00011124-5',
                'email' => 'hnjmdelosrios@salud.gob.ve',
                'telefono' => '0212-5742011',
                'nombre_contacto' => 'Dra. Ana Fernández',
                'direccion' => 'Av. Baralt, San Bernardino, Caracas',
                'dependencia' => 'MPPS',
                'estado' => 'Distrito Capital',
                'municipio' => 'Libertador',
                'parroquia' => 'San José',
                'tipo' => 'Tipo III',
                'status' => 'activo',
                'ubicacion' => [
                    'lat' => 10.5089,
                    'lng' => -66.9049
                ]
            ]
        ];

        foreach ($hospitals as $hospital) {
            Hospital::create($hospital);
        }

        $this->command->info('Hospitales de ejemplo creados exitosamente.');
    }
}
