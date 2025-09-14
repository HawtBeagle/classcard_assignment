<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueryOptimizationController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('users/search', [QueryOptimizationController::class, 'search']);

Route::get('/dashboard/{branchId}', [DashboardController::class, 'show']);
