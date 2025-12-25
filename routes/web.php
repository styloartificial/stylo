<?php

use App\Models\AppHistory;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/download/android', function () {
    $apkUrl = AppHistory::latest()->first()->apk_url;

    return redirect()->away($apkUrl);
})->name('download.android');
