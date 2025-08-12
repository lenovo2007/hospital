<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\FarmaciaController;
use App\Http\Controllers\MiniAlmacenController;
use App\Http\Controllers\AlmacenPrincipalController;
use App\Http\Controllers\AlmacenCentralController;

// Autenticación con token
Route::post('/login', [AuthController::class, 'login']);


Route::middleware(['auth:sanctum','crud.perms'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Hospitales CRUD
    Route::get('/hospitales/buscar_por_rif', [HospitalController::class, 'buscarPorRif']);
    Route::put('/hospitales/actualizar_por_rif', [HospitalController::class, 'actualizarPorRif']);
    Route::get('/hospitales', [HospitalController::class, 'index']);
    Route::post('/hospitales', [HospitalController::class, 'store']);
    Route::get('/hospitales/{hospital}', [HospitalController::class, 'show']);
    Route::put('/hospitales/{hospital}', [HospitalController::class, 'update']);
    Route::delete('/hospitales/{hospital}', [HospitalController::class, 'destroy']);

    // Sedes CRUD
    Route::get('/sedes', [SedeController::class, 'index']);
    Route::post('/sedes', [SedeController::class, 'store']);
    Route::get('/sedes/{sede}', [SedeController::class, 'show']);
    Route::put('/sedes/{sede}', [SedeController::class, 'update']);
    Route::delete('/sedes/{sede}', [SedeController::class, 'destroy']);

    // Farmacias CRUD
    Route::get('/farmacias', [FarmaciaController::class, 'index']);
    Route::post('/farmacias', [FarmaciaController::class, 'store']);
    Route::get('/farmacias/{farmacia}', [FarmaciaController::class, 'show']);
    Route::put('/farmacias/{farmacia}', [FarmaciaController::class, 'update']);
    Route::delete('/farmacias/{farmacia}', [FarmaciaController::class, 'destroy']);

    // Mini Almacenes CRUD
    Route::get('/mini_almacenes', [MiniAlmacenController::class, 'index']);
    Route::post('/mini_almacenes', [MiniAlmacenController::class, 'store']);
    Route::get('/mini_almacenes/{mini_almacene}', [MiniAlmacenController::class, 'show']);
    Route::put('/mini_almacenes/{mini_almacene}', [MiniAlmacenController::class, 'update']);
    Route::delete('/mini_almacenes/{mini_almacene}', [MiniAlmacenController::class, 'destroy']);

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
});

