<?php

use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('welcome');
});

// Rutas CRUD para usuarios
//Route::resource('users', UserController::class);
/*
GET /users → users.index
GET /users/create → users.create
POST /users → users.store
GET /users/{user} → users.show
GET /users/{user}/edit → users.edit
PUT/PATCH /users/{user} → users.update
DELETE /users/{user} → users.destroy
*/