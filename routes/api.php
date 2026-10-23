<?php

use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\MasterAyam\MasterAyamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Laravel API berjalan!',
        'data' => [
            'application' => config('app.name'),
            'version' => app()->version(),
        ],
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard berhasil diakses.',
            'data' => [
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ],
            ],
        ]);
    });
});

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/users', [UserRoleController::class, 'index']);

        Route::patch(
            '/users/{user}/role',
            [UserRoleController::class, 'updateRole']
        );
    });

Route::prefix('master-ayam')->group(function () {
    Route::get('/', [MasterAyamController::class, 'index']);
    Route::post('/', [MasterAyamController::class, 'store']);
    Route::get('/{masterAyam}', [MasterAyamController::class, 'show']);
    Route::put('/{masterAyam}', [MasterAyamController::class, 'update']);
    Route::delete('/{masterAyam}', [MasterAyamController::class, 'destroy']);
});