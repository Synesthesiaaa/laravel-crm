<?php

use App\Http\Controllers\Api\V1\DispositionCodesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('email/postmark', \App\Http\Controllers\Email\PostmarkWebhookController::class)->withoutMiddleware(\App\Http\Middleware\AuditUserActivity::class)->middleware('throttle:240,1')->name('email-provider.postmark');

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
