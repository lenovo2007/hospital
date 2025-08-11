<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\FarmaciaController;
use App\Http\Controllers\MiniAlmacenController;
use App\Http\Controllers\AlmacenPrincipalController;
use App\Http\Controllers\AlmacenCentralController;

// Autenticación con token
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Hospitals CRUD
    Route::get('/hospitals', [HospitalController::class, 'index']);
    Route::post('/hospitals', [HospitalController::class, 'store']);
    Route::get('/hospitals/{hospital}', [HospitalController::class, 'show']);
    Route::put('/hospitals/{hospital}', [HospitalController::class, 'update']);
    Route::delete('/hospitals/{hospital}', [HospitalController::class, 'destroy']);

    // Almacenes CRUD
    Route::get('/almacenes', [AlmacenController::class, 'index']);
    Route::post('/almacenes', [AlmacenController::class, 'store']);
    Route::get('/almacenes/{almacen}', [AlmacenController::class, 'show']);
    Route::put('/almacenes/{almacen}', [AlmacenController::class, 'update']);
    Route::delete('/almacenes/{almacen}', [AlmacenController::class, 'destroy']);

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

