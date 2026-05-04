<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\ScanController;
use App\Http\Controllers\Dashboard\SaveItemController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Api\ScraperController;

// Route middleware guest
Route::middleware('guest')->group(function () {
    Route::prefix('/auth')->group(function () {
        Route::prefix('/register')->group(function () {
            Route::post('/', [RegisterController::class, 'Register']);
            Route::post('/check-email', [RegisterController::class, 'CheckEmail']);
            Route::get('/skin-tone', [RegisterController::class, 'GetSkinTone']);
            Route::get('/body-shape', [RegisterController::class, 'GetBodyShape']);
        });

        Route::prefix('/login')->group(function () {
            Route::post('/', [LoginController::class, 'Login']);
            Route::post('/google', [LoginController::class, 'LoginGoogle']);
            Route::post('/scraper', [LoginController::class, 'LoginScraper']);
        });

        Route::prefix('/forgot-password')->group(function () {
            Route::post('/send-otp', [ForgotPasswordController::class, 'SendOtp']);
            Route::post('/submit-token', [ForgotPasswordController::class, 'SubmitToken']);
            Route::post('/change-password', [ForgotPasswordController::class, 'ChangePassword']);
        });
    });
});

// Route middleware auth
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('core')->group(function () {

        Route::prefix('master')->group(function () {
            Route::get('/scan-categories', [ScanController::class, 'scanCategory']);
        });

        Route::post('/validate-image-by-profile-gender', [ScanController::class, 'validateImageByProfileGender']);
        Route::post('/open-ticket', [ScanController::class, 'openTicket']);
        Route::get('/scan-result/{ticketId}', [ScanController::class, 'getScanResult']);
    });

    Route::post('/log-scrap-process', [ScanController::class, 'logScrapProcess']);
    Route::post('/close-ticket', [ScanController::class, 'closeTicket']);
    // Route::get('/profile', [ProfileController::class, 'index']);
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'index']);
        Route::get('/skin-tone', [ProfileController::class, 'getSkinTone']);
        Route::get('/body-shape', [ProfileController::class, 'getBodyShape']); 
        Route::patch('/', [ProfileController::class, 'update']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::post('/change-img-url', [ProfileController::class, 'changeImgUrl']);
    });

    Route::prefix('saved')->group(function () {
        Route::get('/', [SaveItemController::class, 'index']);
        Route::post('/', [SaveItemController::class, 'store']);
    });
});

Route::prefix('scraper')->middleware(['auth:sanctum', 'role:Scraper'])->group(function () {
    Route::get('get-oldest-ticket-request', [ScraperController::class, 'getOldestTicketRequest']);
    Route::post('set-done-ticket-request', [ScraperController::class, 'setDoneTicketRequest']);
});