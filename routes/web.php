<?php

use Ghdj\VisitorTracker\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

$prefix = config('visitor-tracker.dashboard.prefix', 'admin/visitor-tracker');
$configuredMiddleware = config('visitor-tracker.dashboard.middleware', ['web']);

// Combine configured middleware with our authorization middleware
$middleware = array_merge($configuredMiddleware, ['visitor-tracker-auth']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('visitor-tracker.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/stats', [DashboardController::class, 'stats'])->name('stats');
    });
