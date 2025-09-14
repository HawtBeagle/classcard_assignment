<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueryOptimizationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('users/search', [QueryOptimizationController::class, 'search']);