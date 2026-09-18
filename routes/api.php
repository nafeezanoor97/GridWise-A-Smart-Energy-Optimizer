<?php

use App\Http\Controllers\EnergyOptimizationController;
use App\Services\LlmInterpreterService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::post('/optimize-energy', [
    EnergyOptimizationController::class,
    'optimize'
]);

