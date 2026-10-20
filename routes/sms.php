<?php

use App\Http\Controllers\Admin\SmsController;
use Illuminate\Support\Facades\Route;

Route::prefix('sms')->name('sms.')->controller(SmsController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/history/filter', 'historyFilter')->name('history-filter');
    Route::get('/recipients-template', 'csvTemplate')->name('recipients-template');
    Route::get('/report', 'report')->middleware('throttle:5,1')->name('report');
    Route::post('/campaigns', 'store')->name('store');
    Route::get('/campaigns/{campaign}', 'review')->name('review');
    Route::put('/campaigns/{campaign}', 'update')->name('update');
    Route::post('/campaigns/{campaign}/reset-audience', 'resetAudience')->name('reset-audience');
    Route::post('/campaigns/{campaign}/recipients', 'recipient')->name('recipient');
    Route::post('/campaigns/{campaign}/import', 'import')->middleware('throttle:5,1')->name('import');
    Route::post('/campaigns/{campaign}/leads', 'leads')->middleware('throttle:5,1')->name('leads');
    Route::post('/campaigns/{campaign}/confirm', 'confirm')->middleware('throttle:3,1')->name('confirm');
    Route::post('/campaigns/{campaign}/test', 'testMessage')->middleware('throttle:3,1')->name('test');
    Route::post('/campaigns/{campaign}/{action}', 'transition')->whereIn('action', ['pause', 'resume', 'cancel'])->name('transition');
    Route::post('/consents', 'consent')->name('consent');
    Route::post('/suppressions', 'revoke')->name('revoke');
    Route::post('/emergency-stop', 'emergencyStop')->middleware('throttle:3,1')->name('emergency-stop');
    Route::post('/messages/{message}/reconcile', 'reconcile')->middleware('throttle:5,1')->name('reconcile');
    Route::post('/gateways', 'gateway')->name('gateways.store');
    Route::put('/gateways/{gateway}', 'gateway')->name('gateways.update');
    Route::post('/gateways/{gateway}/channels', 'channel')->name('channels.store');
    Route::post('/gateways/{gateway}/test', 'connection')->middleware('throttle:3,1')->name('connection');
    Route::post('/templates', 'template')->name('templates.store');
    Route::put('/templates/{template}', 'template')->name('templates.update');
    Route::post('/messages/{message}/read', 'read')->name('read');
});
