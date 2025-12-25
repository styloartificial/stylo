<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');

    $apkUrl = "https://google.com";
    return redirect()->away($apkUrl);
})->name('download.android');
