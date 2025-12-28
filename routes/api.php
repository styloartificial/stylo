<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route middleware guest
Route::middleware('guest')->group(function() {
    Route::prefix('/auth')->group(function() {
        Route::prefix('/register')->group(function() {
            Route::post('/', [RegisterController::class, 'Register']);
            Route::post('/check-email', [RegisterController::class, 'CheckEmail']);
            Route::get('/skin-tone', [RegisterController::class, 'GetSkinTone']);
        });

        Route::post('/login', [LoginController::class, 'Login']);

        Route::post('/forgot-password')->group(function() {
            Route::post('/send-otp', [ForgotPasswordController::class, 'SendOtp']);
        });
    });
});

// Route middleware auth
Route::middleware('auth:sanctum')->group(function() {

});