<?php

use App\Http\Controllers\Api\Admin\UserRoleController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\MasterAyam\MasterAyamController;
use App\Http\Controllers\Api\MasterKandang\MasterKandangController;
use App\Http\Controllers\Api\MasterLokasi\MasterLokasiController;
use App\Http\Controllers\Api\PeriodeClosing\PeriodeClosingController;
use App\Http\Controllers\Api\TransaksiAyamBulanan\TransaksiAyamBulananController;
use App\Http\Controllers\Api\LedgerImportController;
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
    Route::prefix('imports')
        ->name('imports.')
        ->group(function () {

            // Upload Excel file
            Route::post('/ledger', [
                LedgerImportController::class,
                'store',
            ])
                ->middleware('throttle:5,1')
                ->name('ledger.store');

            // Get import status and progress
            Route::get('/ledger/{import}', [
                LedgerImportController::class,
                'show',
            ])
                ->whereNumber('import')
                ->name('ledger.show');

            // Get import errors
            Route::get('/ledger/{import}/errors', [
                LedgerImportController::class,
                'errors',
            ])
                ->whereNumber('import')
                ->name('ledger.errors');

            // Get imported ledger entries
            Route::get('/ledger-entries', [
                LedgerImportController::class,
                'entries',
            ])
                ->name('ledger.entries');
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

Route::prefix('master-kandang')->group(function () {
    Route::get('/', [MasterKandangController::class, 'index']);
    Route::post('/', [MasterKandangController::class, 'store']);
    Route::get('/{masterKandang}', [MasterKandangController::class, 'show']);
    Route::put('/{masterKandang}', [MasterKandangController::class, 'update']);
    Route::delete('/{masterKandang}', [MasterKandangController::class, 'destroy']);
});

Route::prefix('master-lokasi')->group(function () {
    Route::get('/', [MasterLokasiController::class, 'index']);
    Route::post('/', [MasterLokasiController::class, 'store']);
    Route::get('/{masterLokasi}', [MasterLokasiController::class, 'show']);
    Route::put('/{masterLokasi}', [MasterLokasiController::class, 'update']);
    Route::delete('/{masterLokasi}', [MasterLokasiController::class, 'destroy']);
});

Route::prefix('periode-closing')->group(function () {
    Route::get('/', [PeriodeClosingController::class, 'index']);
    Route::post('/', [PeriodeClosingController::class, 'store']);
    Route::get('/{periodeClosing}', [PeriodeClosingController::class, 'show']);
    Route::put('/{periodeClosing}', [PeriodeClosingController::class, 'update']);
    Route::delete('/{periodeClosing}', [PeriodeClosingController::class, 'destroy']);
});

Route::prefix('transaksi-ayam-bulanan')->group(function () {
    Route::get('/', [TransaksiAyamBulananController::class, 'index']);
    Route::post('/', [TransaksiAyamBulananController::class, 'store']);
    Route::get('/{transaksiAyamBulanan}', [TransaksiAyamBulananController::class, 'show']);
    Route::put('/{transaksiAyamBulanan}', [TransaksiAyamBulananController::class, 'update']);
    Route::delete('/{transaksiAyamBulanan}', [TransaksiAyamBulananController::class, 'destroy']);
});