<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\AlmacenController;

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
});

