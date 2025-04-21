<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::resource('projects', ProjectController::class)->middleware('auth:sanctum');
Route::resource('tasks', TaskController::class)->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('tasks_count', [TaskController::class, 'tasks_count']);
});

Route::post('users', [UserController::class, 'store']);

Route::resource('users', UserController::class)->only([
    'update',
    'destroy',
])->middleware('auth:sanctum');