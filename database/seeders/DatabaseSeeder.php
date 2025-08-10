<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear 3 usuarios de prueba
        User::factory()->create([
            'tipo' => 'administrativo',
            'rol' => 'admin',
            'nombre' => 'Admin',
            'apellido' => 'Principal',
            'cedula' => '0000000001',
            'telefono' => '0999999999',
            'direccion' => 'Av. Siempre Viva 123',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'tipo' => 'doctor',
            'rol' => 'user',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'cedula' => '0000000002',
            'telefono' => '0988888888',
            'direccion' => 'Calle 1 y Av 2',
            'email' => 'doctor@example.com',
        ]);

        User::factory()->create([
            'tipo' => 'paciente',
            'rol' => 'user',
            'nombre' => 'María',
            'apellido' => 'García',
            'cedula' => '0000000003',
            'telefono' => null,
            'direccion' => null,
            'email' => 'paciente@example.com',
        ]);
    }
}
