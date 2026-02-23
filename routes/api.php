<?php

use App\Http\Controllers\Api\V1\DispositionCodesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| API v1 (token-based: use Bearer token for external integrations)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('disposition-codes', DispositionCodesController::class)->name('api.v1.disposition-codes');
});
