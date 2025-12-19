<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Auth routes (session + cookies)
Route::post('/login', [UserAuthController::class, 'login']);
Route::post('/logout', [UserAuthController::class, 'logout']);
Route::get('/check-auth', [UserAuthController::class, 'checkAuth']);
