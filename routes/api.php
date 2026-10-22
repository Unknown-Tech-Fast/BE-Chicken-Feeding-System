<?php

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