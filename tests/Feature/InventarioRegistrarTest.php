<?php

use App\Models\User;
use App\Models\Hospital;
use App\Models\Sede;
use App\Models\Insumo;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\assertDatabaseHas;

it('registra inventario en almacen central y refleja en tablas relacionadas', function () {
    // Deshabilitar middleware de permisos CRUD para esta prueba
    $this->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);

    // Autenticación con Sanctum
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Preparar datos base: hospital, sede, insumo
    $hospital = Hospital::first() ?? Hospital::create([
        'nombre' => 'Hospital de Pruebas',
        'rif' => 'J-11111111-1',
        'direccion' => 'Ciudad',
        'telefono' => '0000000000',
        'email' => 'pruebas@hospital.test',
        'tipo' => 'publico',
        'status' => 'activo',
    ]);

    $sede = Sede::firstOrCreate([
        'hospital_id' => $hospital->id,
        'nombre' => 'Sede Central Test',
    ], [
        'tipo_almacen' => 'Almacén Central',
        'status' => 'activo',
    ]);

    $insumo = Insumo::firstOrCreate([
        'codigo' => 'INS-TEST-001',
    ], [
        'nombre' => 'Insumo de Prueba',
        'tipo' => 'descartable',
        'unidad_medida' => 'unidad',
        'cantidad_por_paquete' => 1,
        'status' => 'activo',
    ]);

    // Ejecutar petición
    $payload = [
        'insumo_id' => $insumo->id,
        'lote_cod' => 'LOT-TEST-0001',
        'fecha_vencimiento' => now()->addYear()->toDateString(),
        'almacen_tipo' => 'almacenCent',
        'cantidad' => 25,
        'hospital_id' => $hospital->id,
        'sede_id' => $sede->id,
    ];

    $response = postJson('/api/inventario/registrar', $payload);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->where('status', true)
            ->has('data.lote_id')
            ->has('data.lote_almacen_id')
        );

    // Validar inserciones en BD
    // 1) Lote creado
    assertDatabaseHas('lotes', [
        'numero_lote' => 'LOT-TEST-0001',
        'hospital_id' => $hospital->id,
    ]);

    // 2) Lote_almacen creado con sede y cantidad
    assertDatabaseHas('lotes_almacenes', [
        'cantidad' => 25,
        'hospital_id' => $hospital->id,
        'sede_id' => $sede->id,
    ]);

    // 3) Registro en almacenes_centrales acorde al nuevo esquema reducido
    assertDatabaseHas('almacenes_centrales', [
        'sede_id' => $sede->id,
        'hospital_id' => $hospital->id,
        'cantidad' => 25,
        'status' => 1,
    ]);
});
