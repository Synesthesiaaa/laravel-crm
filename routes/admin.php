<?php

use Illuminate\Support\Facades\Route;

// This file is loaded from the authenticated campaign route group in web.php.
Route::middleware('role:Team Leader,Admin,Super Admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard-layout', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'updateLayout'])
        ->middleware('role:Admin,Super Admin')
        ->name('dashboard-layout.update');
    Route::get('supervisor', [\App\Http\Controllers\Admin\SupervisorController::class, 'index'])->name('supervisor');
    Route::get('telephony-monitor', [\App\Http\Controllers\Admin\TelephonyMonitorController::class, 'index'])->name('telephony-monitor');
    Route::get('attendance', [\App\Http\Controllers\Admin\AttendanceLogsController::class, 'index'])->name('attendance.index');
    Route::get('records', [\App\Http\Controllers\Admin\RecordsListController::class, 'index'])->name('records.index');
    Route::get('data-master', [\App\Http\Controllers\Admin\DataMasterController::class, 'index'])->name('data-master.index');
    Route::get('data-master/edit/{id}', [\App\Http\Controllers\Admin\DataMasterController::class, 'edit'])->name('data-master.edit')->where('id', '[0-9]+');
    Route::post('data-master/update', [\App\Http\Controllers\Admin\DataMasterController::class, 'update'])->name('data-master.update');
    Route::post('data-master/delete', [\App\Http\Controllers\Admin\DataMasterController::class, 'destroy'])->name('data-master.destroy');
    Route::get('capture-records', [\App\Http\Controllers\Admin\CaptureRecordsController::class, 'index'])->name('capture-records.index');
    Route::get('capture-records/edit/{record}', [\App\Http\Controllers\Admin\CaptureRecordsController::class, 'edit'])->name('capture-records.edit')->where('record', '[0-9]+');
    Route::post('capture-records/update/{record}', [\App\Http\Controllers\Admin\CaptureRecordsController::class, 'update'])->name('capture-records.update')->where('record', '[0-9]+');
    Route::post('capture-records/delete', [\App\Http\Controllers\Admin\CaptureRecordsController::class, 'destroy'])->name('capture-records.destroy');
    Route::post('capture-records/export', [\App\Http\Controllers\Admin\CaptureRecordsController::class, 'export'])->name('capture-records.export');
    Route::get('disposition-records', [\App\Http\Controllers\Admin\DispositionRecordsController::class, 'index'])->name('disposition-records.index');
    Route::get('disposition-codes', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'index'])->name('disposition-codes.index');
    Route::post('disposition-codes', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'store'])->name('disposition-codes.store');
    Route::put('disposition-codes/{id}', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'update'])->name('disposition-codes.update');
    Route::post('disposition-codes/delete', [\App\Http\Controllers\Admin\DispositionCodesController::class, 'destroy'])->name('disposition-codes.destroy');
    Route::get('field-logic', [\App\Http\Controllers\Admin\FieldLogicController::class, 'index'])->name('field-logic.index');
    Route::get('field-logic/{formField}/edit', [\App\Http\Controllers\Admin\FieldLogicController::class, 'edit'])->name('field-logic.edit');
    Route::post('field-logic', [\App\Http\Controllers\Admin\FieldLogicController::class, 'store'])->name('field-logic.store');
    Route::put('field-logic/{id}', [\App\Http\Controllers\Admin\FieldLogicController::class, 'update'])->name('field-logic.update');
    Route::post('field-logic/delete', [\App\Http\Controllers\Admin\FieldLogicController::class, 'destroy'])->name('field-logic.destroy');
    Route::get('extraction', [\App\Http\Controllers\Admin\ExtractionController::class, 'index'])->name('extraction.index');
    Route::post('extraction', [\App\Http\Controllers\Admin\ExtractionController::class, 'export'])->name('extraction.export');

    Route::middleware('role:Super Admin')->group(function () {
        Route::get('configuration', [\App\Http\Controllers\Admin\ConfigurationController::class, 'index'])->name('configuration');
        Route::get('activity-log', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-log.index');
        Route::get('activity-log/entries', [\App\Http\Controllers\Admin\ActivityLogController::class, 'entries'])->name('activity-log.entries');
        Route::post('configuration/retention', [\App\Http\Controllers\Admin\DataRetentionController::class, 'store'])->name('configuration.retention.store');
        Route::post('configuration/retention/{policy}/run', [\App\Http\Controllers\Admin\DataRetentionController::class, 'run'])->name('configuration.retention.run');
        Route::delete('configuration/retention/{policy}', [\App\Http\Controllers\Admin\DataRetentionController::class, 'destroy'])->name('configuration.retention.destroy');
        Route::post('configuration/branding', [\App\Http\Controllers\Admin\ConfigurationController::class, 'updateBranding'])->name('configuration.branding.update');
        Route::post('configuration/telephony-features', [\App\Http\Controllers\Admin\ConfigurationController::class, 'updateTelephonyFeatures'])->name('configuration.telephony-features.update');
        Route::post('configuration/telephony-diagnostics', \App\Http\Controllers\Admin\TelephonyDiagnosticsController::class)->name('configuration.telephony-diagnostics');
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
        Route::get('campaigns/{campaign}/vicidial-campaigns', [\App\Http\Controllers\Admin\CampaignsController::class, 'vicidialCampaigns'])->name('campaigns.vicidial-campaigns');
        Route::put('campaigns/{campaign}/vicidial-mapping', [\App\Http\Controllers\Admin\CampaignsController::class, 'updateVicidialMapping'])->name('campaigns.vicidial-mapping.update');
        Route::post('campaigns/delete', [\App\Http\Controllers\Admin\CampaignsController::class, 'destroy'])->name('campaigns.destroy');
        Route::get('forms', [\App\Http\Controllers\Admin\FormsController::class, 'index'])->name('forms.index');
        Route::post('forms', [\App\Http\Controllers\Admin\FormsController::class, 'store'])->name('forms.store');
        Route::put('forms/{form}', [\App\Http\Controllers\Admin\FormsController::class, 'update'])->name('forms.update');
        Route::post('forms/delete', [\App\Http\Controllers\Admin\FormsController::class, 'destroy'])->name('forms.destroy');
        Route::get('lead-hopper', [\App\Http\Controllers\Admin\LeadHopperController::class, 'index'])->name('lead-hopper.index');
        Route::post('lead-hopper/import', [\App\Http\Controllers\Admin\LeadHopperController::class, 'import'])->name('lead-hopper.import');
        Route::get('agent-screen', [\App\Http\Controllers\Admin\AgentScreenController::class, 'index'])->name('agent-screen.index');
        Route::post('agent-screen', [\App\Http\Controllers\Admin\AgentScreenController::class, 'store'])->name('agent-screen.store');
        Route::post('agent-screen/webform', [\App\Http\Controllers\Admin\AgentScreenController::class, 'saveWebform'])->name('agent-screen.webform.update');
        Route::put('agent-screen/{field}', [\App\Http\Controllers\Admin\AgentScreenController::class, 'update'])->name('agent-screen.update');
        Route::post('agent-screen/delete', [\App\Http\Controllers\Admin\AgentScreenController::class, 'destroy'])->name('agent-screen.destroy');
        Route::get('attendance-statuses', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'index'])->name('attendance-statuses.index');
        Route::post('attendance-statuses', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'store'])->name('attendance-statuses.store');
        Route::put('attendance-statuses/{id}', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'update'])->name('attendance-statuses.update');
        Route::post('attendance-statuses/delete', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'destroy'])->name('attendance-statuses.destroy');
    });
});
