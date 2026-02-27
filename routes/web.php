<?php

use App\Http\Controllers\Api\VicidialProxyController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\RecordsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// AMI webhook (CSRF exempt, no auth) - Asterisk posts hangup/CDR events; validate via X-Webhook-Secret if configured
Route::post('api/webhooks/ami', \App\Http\Controllers\Api\AmiWebhookController::class)->name('api.webhooks.ami');

// Telephony health (for monitoring; optionally restrict by IP in production)
Route::get('api/telephony/health', \App\Http\Controllers\Api\TelephonyHealthController::class)->name('api.telephony.health');

// WebSocket config for frontend (public, returns connection params)
Route::get('api/websocket/health', \App\Http\Controllers\Api\WebsocketHealthController::class)->name('api.websocket.health');

Route::middleware(['auth', 'campaign'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('records', [RecordsController::class, 'index'])->name('records.index');
    Route::get('agent', [AgentController::class, 'index'])->name('agent.index');
    Route::get('api/vicidial/proxy', VicidialProxyController::class)->name('api.vicidial.proxy')->middleware('throttle:vicidial');
    Route::post('api/call/dial', [\App\Http\Controllers\Api\CallController::class, 'dial'])->name('api.call.dial')->middleware('throttle:vicidial');
    Route::post('api/call/predictive-dial', [\App\Http\Controllers\Api\CallController::class, 'predictiveDial'])->name('api.call.predictive-dial')->middleware('throttle:vicidial');
    Route::post('api/call/hangup', [\App\Http\Controllers\Api\CallController::class, 'hangup'])->name('api.call.hangup')->middleware('throttle:api');
    Route::get('api/call/status', [\App\Http\Controllers\Api\CallController::class, 'status'])->name('api.call.status')->middleware('throttle:api');
    Route::get('api/sip/credentials', [\App\Http\Controllers\Api\SipCredentialsController::class, 'show'])->name('api.sip.credentials')->middleware('throttle:api');
    Route::post('api/agent/capture', [\App\Http\Controllers\Api\AgentCaptureController::class, 'store'])->name('api.agent.capture')->middleware('throttle:api');
    Route::get('api/leads/next', \App\Http\Controllers\Api\NextLeadController::class)->name('api.leads.next')->middleware('throttle:api');
    Route::get('api/disposition-codes', \App\Http\Controllers\Api\DispositionController::class)->name('api.disposition.codes')->middleware('throttle:api');
    Route::get('api/notifications', \App\Http\Controllers\Api\NotificationsController::class)->name('api.notifications')->middleware('throttle:api');
    Route::get('api/search', \App\Http\Controllers\Api\GlobalSearchController::class)->name('api.search')->middleware('throttle:api');
    Route::get('api/supervisor/agents', \App\Http\Controllers\Api\SupervisorAgentsController::class)->name('api.supervisor.agents')->middleware('throttle:api');
    Route::post('api/notifications/read-all', \App\Http\Controllers\Api\MarkNotificationsReadController::class)->name('api.notifications.read-all')->middleware('throttle:api');
    Route::post('api/disposition/save', \App\Http\Controllers\Api\SaveDispositionController::class)->name('api.disposition.save')->middleware('throttle:api');
    Route::post('api/client-errors', fn() => response()->json(['ok' => true]))->name('api.client-errors');
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');

    // Admin: Team Leader, Admin, or Super Admin
    Route::middleware('role:Team Leader,Admin,Super Admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('supervisor', [\App\Http\Controllers\Admin\SupervisorController::class, 'index'])->name('supervisor');
        Route::get('telephony-monitor', [\App\Http\Controllers\Admin\TelephonyMonitorController::class, 'index'])->name('telephony-monitor');
        Route::get('attendance', [\App\Http\Controllers\Admin\AttendanceLogsController::class, 'index'])->name('attendance.index');
        Route::get('records', [\App\Http\Controllers\Admin\RecordsListController::class, 'index'])->name('records.index');
        Route::get('data-master', [\App\Http\Controllers\Admin\DataMasterController::class, 'index'])->name('data-master.index');
        Route::get('data-master/edit/{id}', [\App\Http\Controllers\Admin\DataMasterController::class, 'edit'])->name('data-master.edit')->where('id', '[0-9]+');
        Route::post('data-master/update', [\App\Http\Controllers\Admin\DataMasterController::class, 'update'])->name('data-master.update');
        Route::post('data-master/delete', [\App\Http\Controllers\Admin\DataMasterController::class, 'destroy'])->name('data-master.destroy');
        Route::get('disposition-records', [\App\Http\Controllers\Admin\DispositionRecordsController::class, 'index'])->name('disposition-records.index');
        Route::get('disposition-codes', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'index'])->name('disposition-codes.index');
        Route::post('disposition-codes', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'store'])->name('disposition-codes.store');
        Route::put('disposition-codes/{id}', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'update'])->name('disposition-codes.update');
        Route::post('disposition-codes/delete', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'destroy'])->name('disposition-codes.destroy');
        Route::get('field-logic', [\App\Http\Controllers\Admin\FieldLogicController::class, 'index'])->name('field-logic.index');
        Route::post('field-logic', [\App\Http\Controllers\Admin\FieldLogicController::class, 'store'])->name('field-logic.store');
        Route::put('field-logic/{id}', [\App\Http\Controllers\Admin\FieldLogicController::class, 'update'])->name('field-logic.update');
        Route::post('field-logic/delete', [\App\Http\Controllers\Admin\FieldLogicController::class, 'destroy'])->name('field-logic.destroy');
        Route::get('extraction', [\App\Http\Controllers\Admin\ExtractionController::class, 'index'])->name('extraction.index');
        Route::post('extraction', [\App\Http\Controllers\Admin\ExtractionController::class, 'export'])->name('extraction.export');

        // Super Admin only
        Route::middleware('role:Super Admin')->group(function () {
            Route::get('configuration', [\App\Http\Controllers\Admin\ConfigurationController::class, 'index'])->name('configuration');
            Route::get('users', [\App\Http\Controllers\Admin\UsersController::class, 'index'])->name('users.index');
            Route::post('users', [\App\Http\Controllers\Admin\UsersController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [\App\Http\Controllers\Admin\UsersController::class, 'update'])->name('users.update');
            Route::post('users/delete', [\App\Http\Controllers\Admin\UsersController::class, 'destroy'])->name('users.destroy');
            Route::get('vicidial-servers', [\App\Http\Controllers\Admin\VicidialServersController::class, 'index'])->name('vicidial-servers.index');
            Route::post('vicidial-servers', [\App\Http\Controllers\Admin\VicidialServersController::class, 'store'])->name('vicidial-servers.store');
            Route::put('vicidial-servers/{server}', [\App\Http\Controllers\Admin\VicidialServersController::class, 'update'])->name('vicidial-servers.update');
            Route::post('vicidial-servers/delete', [\App\Http\Controllers\Admin\VicidialServersController::class, 'destroy'])->name('vicidial-servers.destroy');
            Route::get('campaigns', [\App\Http\Controllers\Admin\CampaignsController::class, 'index'])->name('campaigns.index');
            Route::post('campaigns', [\App\Http\Controllers\Admin\CampaignsController::class, 'store'])->name('campaigns.store');
            Route::put('campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignsController::class, 'update'])->name('campaigns.update');
            Route::post('campaigns/delete', [\App\Http\Controllers\Admin\CampaignsController::class, 'destroy'])->name('campaigns.destroy');
            Route::get('forms', [\App\Http\Controllers\Admin\FormsController::class, 'index'])->name('forms.index');
            Route::post('forms', [\App\Http\Controllers\Admin\FormsController::class, 'store'])->name('forms.store');
            Route::put('forms/{form}', [\App\Http\Controllers\Admin\FormsController::class, 'update'])->name('forms.update');
            Route::post('forms/delete', [\App\Http\Controllers\Admin\FormsController::class, 'destroy'])->name('forms.destroy');
            Route::get('agent-screen', [\App\Http\Controllers\Admin\AgentScreenController::class, 'index'])->name('agent-screen.index');
            Route::post('agent-screen', [\App\Http\Controllers\Admin\AgentScreenController::class, 'store'])->name('agent-screen.store');
            Route::put('agent-screen/{field}', [\App\Http\Controllers\Admin\AgentScreenController::class, 'update'])->name('agent-screen.update');
            Route::post('agent-screen/delete', [\App\Http\Controllers\Admin\AgentScreenController::class, 'destroy'])->name('agent-screen.destroy');
        });
    });
    Route::get('forms/{type}', [FormController::class, 'show'])->name('forms.show')->where('type', '[a-z_]+');
    Route::post('forms/submit', [FormController::class, 'store'])->name('forms.store')->middleware('throttle:form-submit');
});
