<?php

use App\Http\Controllers\ExtractionController;
use App\Http\Middleware\AuthorizeExtraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/extractions', ExtractionController::class)
    ->middleware(AuthorizeExtraction::class);
