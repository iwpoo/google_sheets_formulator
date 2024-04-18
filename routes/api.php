<?php

use App\Http\Controllers\API\v1\AuthController;
use App\Http\Controllers\API\v1\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/v1/tasks/{task_id}', [TaskController::class, 'show']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
