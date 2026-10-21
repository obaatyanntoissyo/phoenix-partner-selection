<?php

use App\Http\Controllers\Api\IntakeController;
use Illuminate\Support\Facades\Route;

Route::post('/intake/applications', [IntakeController::class, 'store'])
    ->middleware('throttle:intake')
    ->name('api.intake');
