<?php

use App\Http\Controllers\API\v1\AuthController;
use App\Http\Controllers\API\v1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware(['auth.token'])->group(static function (): void {
        Route::apiResource('tasks', TaskController::class)->only(['store', 'show']);
    });
});
