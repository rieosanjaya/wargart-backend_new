<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', fn () => response()->file(public_path('docs/index.html')));
Route::get('/openapi.yaml', fn () => response()->file(base_path('docs/api/openapi.yaml'), ['Content-Type'=>'application/yaml']));
