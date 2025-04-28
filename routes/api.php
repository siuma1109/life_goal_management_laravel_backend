<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('users', [UserController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    $user = $request->user();
    $user->loadCount([
        'followers' => function ($query) {
            $query->where('deleted_at', null);
        },
        'following' => function ($query) {
            $query->where('deleted_at', null);
        },
    ]);
    return $user;
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
    Route::get('tasks_count_by_date', [TaskController::class, 'tasks_count_by_date']);
    Route::post('tasks/{task}/comments', [TaskController::class, 'storeComment']);
    Route::delete('tasks/{task}/comments/{comment}', [TaskController::class, 'destroyComment']);
    Route::put('tasks/{task}/comments/{comment}', [TaskController::class, 'updateComment']);
    Route::get('tasks/{task}/comments', [TaskController::class, 'getComments']);
    Route::get('tasks_count', [TaskController::class, 'tasks_count']);

    Route::get('explore/tasks', [TaskController::class, 'exploreTasks']);
    Route::post('tasks/{task}/like', [TaskController::class, 'like']);

    /**
     * Users
     */
    Route::resource('users', UserController::class)->only([
        'update',
        'destroy',
    ]);

    Route::get('users/{user}/followers', [UserController::class, 'getFollowers']);
    Route::get('users/{user}/following', [UserController::class, 'getFollowing']);

    Route::get('projects_list', [ProjectController::class, 'getProjectsListWithPagination']);
    Route::get('users_list', [UserController::class, 'getUsersList']);
    Route::get('tasks_list', [TaskController::class, 'getTasksListWithPagination']);
    Route::post('users/{user}/follow', [UserController::class, 'followUser']);

    /**
     * Feeds
     */
    Route::resource('feeds', FeedController::class)->only([
        'index',
    ]);
    Route::post('feeds/{feed}/comments', [FeedController::class, 'storeComment']);
    Route::get('feeds/{feed}/comments', [FeedController::class, 'getComments']);
    Route::post('feeds/{feed}/like', [FeedController::class, 'like']);
});
