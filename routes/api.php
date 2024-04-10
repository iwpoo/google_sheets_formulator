<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/register', [AuthController::class, 'register'])->name('api.register');

Route::middleware('auth:sanctum')->get('/ss', function () {
    return 'test';
})->name('test');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
