<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\AlmacenPrincipalController;
use App\Http\Controllers\AlmacenCentralController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\AlmacenFarmaciaController;
use App\Http\Controllers\AlmacenParaleloController;
use App\Http\Controllers\AlmacenServiciosAtencionesController;
use App\Http\Controllers\AlmacenServiciosApoyoController;

// Autenticación con token
// Test route to check if API is working
Route::get('/test', function() {
    return response()->json([
        'status' => true,
        'message' => 'API is working!',
        'data' => [
            'version' => '1.0',
            'timestamp' => now()
        ]
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
// Recuperación de contraseña (público)
Route::post('/users/password/forgot', [UserController::class, 'forgotPassword']);
Route::post('/users/password/reset', [UserController::class, 'resetPassword']);


Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckCrudPermissions::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    // Identificación por EMAIL y CÉDULA (claridad de rutas)
    Route::get('/users/email/{email}', [UserController::class, 'showByEmail']);
    Route::put('/users/email/{email}', [UserController::class, 'updateByEmail']);
    Route::put('/users/email/{email}/password', [UserController::class, 'passwordByEmail']);
    Route::get('/users/cedula/{cedula}', [UserController::class, 'showByCedula']);
    Route::put('/users/cedula/{cedula}', [UserController::class, 'updateByCedula']);
    Route::put('/users/cedula/{cedula}/password', [UserController::class, 'passwordByCedula']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Hospitales CRUD
    // Identificación por RIF (claridad de rutas)
    Route::get('/hospitales/rif/{rif}', [HospitalController::class, 'showByRif']);
    Route::put('/hospitales/rif/{rif}', [HospitalController::class, 'updateByRif']);
    Route::get('/hospitales', [HospitalController::class, 'index']);
    Route::post('/hospitales', [HospitalController::class, 'store']);
    Route::get('/hospitales/{hospital}', [HospitalController::class, 'show']);
    Route::put('/hospitales/{hospital}', [HospitalController::class, 'update']);
    Route::delete('/hospitales/{hospital}', [HospitalController::class, 'destroy']);

    // Sedes CRUD
    Route::get('/sedes', [SedeController::class, 'index']);
    Route::get('/sedes/hospital/{id}', [SedeController::class, 'byHospital']);
    Route::post('/sedes', [SedeController::class, 'store']);
    Route::get('/sedes/{sede}', [SedeController::class, 'show']);
    Route::put('/sedes/{sede}', [SedeController::class, 'update']);
    Route::delete('/sedes/{sede}', [SedeController::class, 'destroy']);

    // Almacenes Principales CRUD
    Route::get('/almacenes_principales', [AlmacenPrincipalController::class, 'index']);
    Route::post('/almacenes_principales', [AlmacenPrincipalController::class, 'store']);
    Route::get('/almacenes_principales/{almacenes_principale}', [AlmacenPrincipalController::class, 'show']);
    Route::put('/almacenes_principales/{almacenes_principale}', [AlmacenPrincipalController::class, 'update']);
    Route::delete('/almacenes_principales/{almacenes_principale}', [AlmacenPrincipalController::class, 'destroy']);

    // Almacenes Centrales CRUD
    Route::get('/almacenes_centrales', [AlmacenCentralController::class, 'index']);
    Route::post('/almacenes_centrales', [AlmacenCentralController::class, 'store']);
    Route::get('/almacenes_centrales/{almacenes_centrale}', [AlmacenCentralController::class, 'show']);
    Route::put('/almacenes_centrales/{almacenes_centrale}', [AlmacenCentralController::class, 'update']);
    Route::delete('/almacenes_centrales/{almacenes_centrale}', [AlmacenCentralController::class, 'destroy']);

    // Almacenes Farmacia CRUD
    Route::get('/almacenes_farmacia', [AlmacenFarmaciaController::class, 'index']);
    Route::post('/almacenes_farmacia', [AlmacenFarmaciaController::class, 'store']);
    Route::get('/almacenes_farmacia/{almacenes_farmacium}', [AlmacenFarmaciaController::class, 'show']);
    Route::put('/almacenes_farmacia/{almacenes_farmacium}', [AlmacenFarmaciaController::class, 'update']);
    Route::delete('/almacenes_farmacia/{almacenes_farmacium}', [AlmacenFarmaciaController::class, 'destroy']);

    // Almacenes Paralelo CRUD
    Route::get('/almacenes_paralelo', [AlmacenParaleloController::class, 'index']);
    Route::post('/almacenes_paralelo', [AlmacenParaleloController::class, 'store']);
    Route::get('/almacenes_paralelo/{almacenes_paralelo}', [AlmacenParaleloController::class, 'show']);
    Route::put('/almacenes_paralelo/{almacenes_paralelo}', [AlmacenParaleloController::class, 'update']);
    Route::delete('/almacenes_paralelo/{almacenes_paralelo}', [AlmacenParaleloController::class, 'destroy']);

    // Almacenes Servicios de Atenciones CRUD
    Route::get('/almacenes_servicios_atenciones', [AlmacenServiciosAtencionesController::class, 'index']);
    Route::post('/almacenes_servicios_atenciones', [AlmacenServiciosAtencionesController::class, 'store']);
    Route::get('/almacenes_servicios_atenciones/{almacenes_servicios_atencione}', [AlmacenServiciosAtencionesController::class, 'show']);
    Route::put('/almacenes_servicios_atenciones/{almacenes_servicios_atencione}', [AlmacenServiciosAtencionesController::class, 'update']);
    Route::delete('/almacenes_servicios_atenciones/{almacenes_servicios_atencione}', [AlmacenServiciosAtencionesController::class, 'destroy']);

    // Almacenes Servicios de Apoyo CRUD
    Route::get('/almacenes_servicios_apoyo', [AlmacenServiciosApoyoController::class, 'index']);
    Route::post('/almacenes_servicios_apoyo', [AlmacenServiciosApoyoController::class, 'store']);
    Route::get('/almacenes_servicios_apoyo/{almacenes_servicios_apoyo}', [AlmacenServiciosApoyoController::class, 'show']);
    Route::put('/almacenes_servicios_apoyo/{almacenes_servicios_apoyo}', [AlmacenServiciosApoyoController::class, 'update']);
    Route::delete('/almacenes_servicios_apoyo/{almacenes_servicios_apoyo}', [AlmacenServiciosApoyoController::class, 'destroy']);

    // Insumos CRUD
    // Identificación por CÓDIGO (claridad de rutas)
    Route::get('/insumos/codigo/{codigo}', [InsumoController::class, 'showByCodigo']);
    Route::put('/insumos/codigo/{codigo}', [InsumoController::class, 'updateByCodigo']);
    // Importación de insumos desde Excel (.xlsx)
    // Requiere auth pero NO pasa por CheckCrudPermissions para evitar bloqueos en importación
    Route::post('/insumos/import', [InsumoController::class, 'importExcel'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::get('/insumos', [InsumoController::class, 'index']);
    Route::post('/insumos', [InsumoController::class, 'store']);
    Route::get('/insumos/{insumo}', [InsumoController::class, 'show']);
    Route::put('/insumos/{insumo}', [InsumoController::class, 'update']);
    Route::delete('/insumos/{insumo}', [InsumoController::class, 'destroy']);
});
