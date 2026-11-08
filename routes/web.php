<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ArenaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
    Route::post('/demo', [AuthController::class, 'demo'])->middleware('throttle:10,1')->name('login.demo');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->name('search');
    Route::post('/monitoring/run', [MonitoringController::class, 'run'])->middleware('can:write')->name('monitoring.run');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/activity/export', [ExportController::class, 'activity'])->name('activity.export');

    Route::get('/arena', [ArenaController::class, 'index'])->name('arena.index');
    Route::post('/arena/conversations', [ArenaController::class, 'store'])->middleware('can:write')->name('arena.store');
    Route::get('/arena/conversations/{conversation}', [ArenaController::class, 'show'])->name('arena.show');
    Route::post('/arena/conversations/{conversation}/messages', [ArenaController::class, 'message'])->middleware('can:write')->middleware('throttle:30,1')->name('arena.message');
    Route::delete('/arena/conversations/{conversation}', [ArenaController::class, 'destroy'])->middleware('can:write')->name('arena.destroy');
    Route::post('/arena/test', [ArenaController::class, 'test'])->middleware('can:write')->name('arena.test');

    Route::prefix('inventory')->group(function () {
        Route::get('/{type}', [InventoryController::class, 'index'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->name('inventory.index');
        Route::get('/{type}/create', [InventoryController::class, 'create'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->middleware('can:write')->name('inventory.create');
        Route::post('/{type}', [InventoryController::class, 'store'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->middleware('can:write')->name('inventory.store');
        Route::get('/{type}/export', [ExportController::class, 'inventory'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->name('inventory.export');
        Route::get('/{type}/{id}/edit', [InventoryController::class, 'edit'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->middleware('can:write')->name('inventory.edit');
        Route::put('/{type}/{id}', [InventoryController::class, 'update'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->middleware('can:write')->name('inventory.update');
        Route::delete('/{type}/{id}', [InventoryController::class, 'destroy'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->middleware('can:write')->name('inventory.destroy');
        Route::get('/{type}/{id}', [InventoryController::class, 'show'])->whereIn('type', ['applications', 'repositories', 'databases', 'infrastructure'])->name('inventory.show');
    });

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('can:write')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('can:write')->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('can:write')->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('can:write')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:write')->name('projects.destroy');
    Route::post('/projects/{project}/tasks', [ProjectController::class, 'storeTask'])->middleware('can:write')->name('projects.tasks.store');
    Route::patch('/projects/{project}/tasks/{task}', [ProjectController::class, 'updateTask'])->middleware('can:write')->name('projects.tasks.update');
    Route::delete('/projects/{project}/tasks/{task}', [ProjectController::class, 'destroyTask'])->middleware('can:write')->name('projects.tasks.destroy');

    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/export', [ExportController::class, 'workspace'])->name('settings.export');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
