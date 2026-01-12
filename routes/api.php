<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\ScanController;
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

        Route::prefix('/login')->group(function() {
            Route::post('/', [LoginController::class, 'Login']);
            Route::post('/google', [LoginController::class, 'LoginGoogle']);
        });

        Route::prefix('/forgot-password')->group(function() {
            Route::post('/send-otp', [ForgotPasswordController::class, 'SendOtp']);
            Route::post('/submit-token', [ForgotPasswordController::class, 'SubmitToken']);
            Route::post('/change-password', [ForgotPasswordController::class, 'ChangePassword']);

        });
    });
});

// Route middleware auth
Route::middleware('auth:sanctum')->group(function() {

    Route::prefix('core/master')->group(function () {
        Route::get('/scan-categories', [ScanController::class, 'scanCategory']);
    });

});