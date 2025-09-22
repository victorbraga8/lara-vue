<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['message' => 'Endpoint funcionando']);
});
