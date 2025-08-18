<?php

use App\Services\BlueFleetService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/teste-token', function (BlueFleetService $bf) {
    $token = $bf->getAccessToken();
    dd($token);
});