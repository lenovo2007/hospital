<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Hospital;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $hospital = Hospital::create([
            'nombre' => 'Hospital de Prueba',
            'rif' => 'J-123456789',
            'direccion' => 'Calle Falsa 123',
            'telefono' => '02121234567',
            'email' => 'test@hospital.com',
            'status' => 'activo',
            'tipo' => 'publico',
            'nombre_completo' => 'Hospital de Prueba Completo',
            'estado' => 'Distrito Capital',
            'municipio' => 'Libertador',
            'parroquia' => 'El Valle',
            'dependencia' => 'Ministerio de Salud',
            'ubicacion' => json_encode(['lat' => 0, 'lng' => 0])
        ]);

        $sede = Sede::create([
            'nombre' => 'Sede Principal',
            'tipo_almacen' => 'principal',
            'hospital_id' => $hospital->id,
            'status' => 'activo'
        ]);

        // Create test user
        $this->user = User::create([
            'nombre' => 'Test',
            'apellido' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'hospital_id' => $hospital->id,
            'sede_id' => $sede->id,
            'status' => 'activo',
            'tipo' => 'administrativo',
            'rol' => 'admin',
            'cedula' => 'V12345678',
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_crud_user' => true
        ]);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'mensaje',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'nombre',
                        'apellido',
                        'email',
                        'hospital' => [
                            'id',
                            'nombre',
                            'rif',
                            'direccion',
                            'telefono',
                            'email',
                            'status'
                        ],
                        'sede' => [
                            'id',
                            'nombre',
                            'tipo_almacen',
                            'hospital_id',
                            'status'
                        ]
                    ]
                ],
                'autenticacion'
            ]);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => false,
                'mensaje' => 'Credenciales inválidas.'
            ]);
    }

    /** @test */
    public function login_requires_email_and_password()
    {
        $response = $this->postJson('/api/login', [
            'email' => '',
            'password' => ''
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'mensaje' => 'Error de validación.'
            ])
            ->assertJsonStructure([
                'errores' => [
                    'email',
                    'password'
                ]
            ]);
    }
}
