<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('users', [UserController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    /**
     * Projects
     */
    Route::resource('projects', ProjectController::class);

    /**
     * Tasks
     */
    Route::resource('tasks', TaskController::class);
    Route::post('tasks/{task}/comments', [TaskController::class, 'storeComment']);
    Route::delete('tasks/{task}/comments/{comment}', [TaskController::class, 'destroyComment']);
    Route::put('tasks/{task}/comments/{comment}', [TaskController::class, 'updateComment']);
    Route::get('tasks/{task}/comments', [TaskController::class, 'getComments']);
    Route::get('tasks_count', [TaskController::class, 'tasks_count']);

    Route::get('explore/tasks', [TaskController::class, 'exploreTasks']);

    /**
     * Users
     */
    Route::resource('users', UserController::class)->only([
        'update',
        'destroy',
    ]);

    Route::get('projects_list', [ProjectController::class, 'getProjectsListWithPagination']);
    Route::get('users_list', [UserController::class, 'getUsersList']);
    Route::post('users/{user}/follow', [UserController::class, 'followUser']);
});
