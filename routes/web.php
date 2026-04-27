<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\Api\VicidialProxyController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('login/pending', [LoginController::class, 'showPendingLogin'])->name('login.pending');
    Route::post('login/pending', [LoginController::class, 'confirmPendingLogin'])->middleware('throttle:login')->name('login.pending.confirm');
    Route::post('login/pending/cancel', [LoginController::class, 'cancelPendingLogin'])->name('login.pending.cancel');
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// AMI webhook (CSRF exempt, no auth) - Asterisk posts hangup/CDR events; validate via X-Webhook-Secret if configured
Route::post('api/webhooks/ami', \App\Http\Controllers\Api\AmiWebhookController::class)->name('api.webhooks.ami');

// ViciDial Agent Events Push webhook (CSRF exempt, no auth) - configured via ViciDial System Settings
Route::post('api/webhooks/vicidial-events', \App\Http\Controllers\Api\VicidialEventsWebhookController::class)->name('api.webhooks.vicidial-events');
Route::get('api/webhooks/vicidial-events', fn () => response()->json(['status' => 'ok', 'method' => 'POST only']));

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
    Route::post('api/call/predictive-dial', [\App\Http\Controllers\Api\CallController::class, 'predictiveDial'])->name('api.call.predictive-dial')->middleware(['throttle:vicidial', 'telephony_feature:predictive_dialing']);
    Route::post('api/call/hangup', [\App\Http\Controllers\Api\CallController::class, 'hangup'])->name('api.call.hangup')->middleware('throttle:api');
    Route::get('api/call/status', [\App\Http\Controllers\Api\CallController::class, 'status'])->name('api.call.status')->middleware('throttle:api');
    Route::post('api/call/dtmf', \App\Http\Controllers\Api\DtmfController::class)->name('api.call.dtmf')->middleware(['throttle:api', 'telephony_feature:dtmf_controls']);
    Route::post('api/call/transfer/blind', [\App\Http\Controllers\Api\TransferController::class, 'blind'])->name('api.call.transfer.blind')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/transfer/warm', [\App\Http\Controllers\Api\TransferController::class, 'warm'])->name('api.call.transfer.warm')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/transfer/local', [\App\Http\Controllers\Api\TransferController::class, 'local'])->name('api.call.transfer.local')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/transfer/leave-3way', [\App\Http\Controllers\Api\TransferController::class, 'leaveThreeWay'])->name('api.call.transfer.leave-3way')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/transfer/hangup-xfer', [\App\Http\Controllers\Api\TransferController::class, 'hangupXfer'])->name('api.call.transfer.hangup-xfer')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/transfer/hangup-both', [\App\Http\Controllers\Api\TransferController::class, 'hangupBoth'])->name('api.call.transfer.hangup-both')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/transfer/vm', [\App\Http\Controllers\Api\TransferController::class, 'vm'])->name('api.call.transfer.vm')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/park', [\App\Http\Controllers\Api\TransferController::class, 'park'])->name('api.call.park')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/grab', [\App\Http\Controllers\Api\TransferController::class, 'grab'])->name('api.call.grab')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/park-ivr', [\App\Http\Controllers\Api\TransferController::class, 'parkIvr'])->name('api.call.park-ivr')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/swap-park', [\App\Http\Controllers\Api\TransferController::class, 'swap'])->name('api.call.swap-park')->middleware(['throttle:vicidial', 'telephony_feature:transfer_controls']);
    Route::post('api/call/recording/start', [\App\Http\Controllers\Api\RecordingController::class, 'start'])->name('api.call.recording.start')->middleware(['throttle:vicidial', 'telephony_feature:recording_controls']);
    Route::post('api/call/recording/stop', [\App\Http\Controllers\Api\RecordingController::class, 'stop'])->name('api.call.recording.stop')->middleware(['throttle:vicidial', 'telephony_feature:recording_controls']);
    Route::get('api/call/recording/status', [\App\Http\Controllers\Api\RecordingController::class, 'status'])->name('api.call.recording.status')->middleware(['throttle:vicidial', 'telephony_feature:recording_controls']);
    Route::get('api/call/recording/lookup', [\App\Http\Controllers\Api\RecordingController::class, 'lookup'])->name('api.call.recording.lookup')->middleware(['throttle:vicidial', 'telephony_feature:recording_controls']);
    Route::post('api/callbacks/schedule', [\App\Http\Controllers\Api\CallbackController::class, 'schedule'])->name('api.callbacks.schedule')->middleware(['throttle:api', 'telephony_feature:callback_controls']);
    Route::post('api/callbacks/remove', [\App\Http\Controllers\Api\CallbackController::class, 'remove'])->name('api.callbacks.remove')->middleware(['throttle:api', 'telephony_feature:callback_controls']);
    Route::get('api/callbacks/info', [\App\Http\Controllers\Api\CallbackController::class, 'info'])->name('api.callbacks.info')->middleware(['throttle:api', 'telephony_feature:callback_controls']);
    Route::post('api/vicidial/session/login', [\App\Http\Controllers\Api\VicidialSessionController::class, 'login'])->name('api.vicidial.session.login')->middleware(['throttle:vicidial', 'telephony_feature:session_controls']);
    Route::post('api/vicidial/session/verify', [\App\Http\Controllers\Api\VicidialSessionController::class, 'verify'])->name('api.vicidial.session.verify')->middleware(['throttle:vicidial', 'telephony_feature:session_controls']);
    Route::post('api/vicidial/session/pause', [\App\Http\Controllers\Api\VicidialSessionController::class, 'pause'])->name('api.vicidial.session.pause')->middleware(['throttle:vicidial', 'telephony_feature:session_controls']);
    Route::post('api/vicidial/session/pause-code', [\App\Http\Controllers\Api\VicidialSessionController::class, 'pauseCode'])->name('api.vicidial.session.pause-code')->middleware(['throttle:vicidial', 'telephony_feature:session_controls']);
    Route::post('api/vicidial/session/logout', [\App\Http\Controllers\Api\VicidialSessionController::class, 'logout'])->name('api.vicidial.session.logout')->middleware(['throttle:vicidial', 'telephony_feature:session_controls']);
    Route::post('api/vicidial/session/ingroups', [\App\Http\Controllers\Api\VicidialSessionController::class, 'ingroups'])->name('api.vicidial.session.ingroups')->middleware(['throttle:vicidial', 'telephony_feature:ingroup_management']);
    Route::get('api/vicidial/session/status', [\App\Http\Controllers\Api\VicidialSessionController::class, 'status'])->name('api.vicidial.session.status')->middleware(['throttle:api', 'telephony_feature:session_controls']);
    Route::get('api/vicidial/session/iframe-url', [\App\Http\Controllers\Api\VicidialSessionController::class, 'iframeUrl'])->name('api.vicidial.session.iframe-url')->middleware(['throttle:api', 'telephony_feature:session_controls']);
    Route::get('api/vicidial/session/agent-campaigns', [\App\Http\Controllers\Api\VicidialSessionController::class, 'agentCampaigns'])->name('api.vicidial.session.agent-campaigns')->middleware(['throttle:api', 'telephony_feature:session_controls']);
    Route::post('api/vicidial/session/select-campaign', [\App\Http\Controllers\Api\VicidialSessionController::class, 'selectCampaign'])->name('api.vicidial.session.select-campaign')->middleware(['throttle:api', 'telephony_feature:session_controls']);
    Route::get('api/leads/search', [\App\Http\Controllers\Api\LeadController::class, 'search'])->name('api.leads.search')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::get('api/leads/info', [\App\Http\Controllers\Api\LeadController::class, 'info'])->name('api.leads.info')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::get('api/leads/field', [\App\Http\Controllers\Api\LeadController::class, 'field'])->name('api.leads.field')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::post('api/leads/add', [\App\Http\Controllers\Api\LeadController::class, 'add'])->name('api.leads.add')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::post('api/leads/update', [\App\Http\Controllers\Api\LeadController::class, 'update'])->name('api.leads.update')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::post('api/leads/switch', [\App\Http\Controllers\Api\LeadController::class, 'switch'])->name('api.leads.switch')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::post('api/leads/update-fields', [\App\Http\Controllers\Api\LeadController::class, 'updateFields'])->name('api.leads.update-fields')->middleware(['throttle:api', 'telephony_feature:lead_tools']);
    Route::get('api/reports/call-status-stats', [\App\Http\Controllers\Api\ReportingController::class, 'callStatusStats'])->name('api.reports.call-status-stats')->middleware('throttle:api');
    Route::get('api/reports/call-dispo-report', [\App\Http\Controllers\Api\ReportingController::class, 'callDispoReport'])->name('api.reports.call-dispo-report')->middleware('throttle:api');
    Route::get('api/reports/agent-stats', [\App\Http\Controllers\Api\ReportingController::class, 'agentStats'])->name('api.reports.agent-stats')->middleware('throttle:api');
    Route::get('api/reports/logged-in-agents', [\App\Http\Controllers\Api\ReportingController::class, 'loggedInAgents'])->name('api.reports.logged-in-agents')->middleware('throttle:api');
    Route::get('api/reports/phone-number-log', [\App\Http\Controllers\Api\ReportingController::class, 'phoneNumberLog'])->name('api.reports.phone-number-log')->middleware('throttle:api');
    Route::get('api/reports/user-group-status', [\App\Http\Controllers\Api\ReportingController::class, 'userGroupStatus'])->name('api.reports.user-group-status')->middleware('throttle:api');
    Route::get('api/reports/in-group-status', [\App\Http\Controllers\Api\ReportingController::class, 'inGroupStatus'])->name('api.reports.in-group-status')->middleware('throttle:api');
    Route::get('api/reports/agent-status', [\App\Http\Controllers\Api\ReportingController::class, 'agentStatus'])->name('api.reports.agent-status')->middleware('throttle:api');
    Route::get('api/sip/credentials', [\App\Http\Controllers\Api\SipCredentialsController::class, 'show'])->name('api.sip.credentials')->middleware('throttle:api');
    Route::post('api/agent/capture', [\App\Http\Controllers\Api\AgentCaptureController::class, 'store'])->name('api.agent.capture')->middleware('throttle:api');
    Route::post('api/agent/record/save', \App\Http\Controllers\Api\SaveAgentRecordController::class)->name('api.agent.record.save')->middleware('throttle:api');
    Route::get('api/agent/submitted-records', [\App\Http\Controllers\Api\AgentSubmittedRecordsController::class, 'index'])->name('api.agent.submitted-records')->middleware('throttle:api');
    Route::get('api/agent/submitted-records/export', [\App\Http\Controllers\Api\AgentSubmittedRecordsController::class, 'export'])->name('api.agent.submitted-records.export')->middleware('throttle:api');
    Route::get('api/leads/next', \App\Http\Controllers\Api\NextLeadController::class)->name('api.leads.next')->middleware('throttle:api');
    Route::get('api/disposition-codes', \App\Http\Controllers\Api\DispositionController::class)->name('api.disposition.codes')->middleware('throttle:api');
    Route::get('api/notifications', \App\Http\Controllers\Api\NotificationsController::class)->name('api.notifications')->middleware('throttle:api');
    Route::get('api/search', \App\Http\Controllers\Api\GlobalSearchController::class)->name('api.search')->middleware('throttle:api');
    Route::get('api/supervisor/agents', \App\Http\Controllers\Api\SupervisorAgentsController::class)->name('api.supervisor.agents')->middleware('throttle:api');
    Route::post('api/notifications/read-all', \App\Http\Controllers\Api\MarkNotificationsReadController::class)->name('api.notifications.read-all')->middleware('throttle:api');
    Route::post('api/disposition/save', \App\Http\Controllers\Api\SaveDispositionController::class)->name('api.disposition.save')->middleware('throttle:api');
    Route::post('api/client-errors', fn () => response()->json(['ok' => true]))->name('api.client-errors');
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('api/attendance/start', [\App\Http\Controllers\Api\AttendanceStatusController::class, 'start'])->name('api.attendance.start')->middleware('throttle:api');
    Route::post('api/attendance/end', [\App\Http\Controllers\Api\AttendanceStatusController::class, 'end'])->name('api.attendance.end')->middleware('throttle:api');
    Route::get('api/attendance/current', [\App\Http\Controllers\Api\AttendanceStatusController::class, 'current'])->name('api.attendance.current')->middleware('throttle:api');
    Route::middleware('role:Team Leader,Admin,Super Admin')->group(function () {
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::post('api/supervisor/monitor', [\App\Http\Controllers\Api\SupervisorTelephonyController::class, 'monitor'])->name('api.supervisor.monitor')->middleware('throttle:vicidial');
        Route::post('api/supervisor/whisper', [\App\Http\Controllers\Api\SupervisorTelephonyController::class, 'whisper'])->name('api.supervisor.whisper')->middleware('throttle:vicidial');
        Route::post('api/supervisor/force-pause', [\App\Http\Controllers\Api\SupervisorTelephonyController::class, 'forcePause'])->name('api.supervisor.force-pause')->middleware('throttle:vicidial');
        Route::post('api/supervisor/force-logout', [\App\Http\Controllers\Api\SupervisorTelephonyController::class, 'forceLogout'])->name('api.supervisor.force-logout')->middleware('throttle:vicidial');
        Route::post('api/supervisor/send-notification', [\App\Http\Controllers\Api\SupervisorTelephonyController::class, 'sendNotification'])->name('api.supervisor.send-notification')->middleware('throttle:vicidial');
    });

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
        Route::get('agent-records', [\App\Http\Controllers\Admin\AgentCallDispositionsController::class, 'index'])->name('agent-records.index');
        Route::get('agent-records/{record}/edit', [\App\Http\Controllers\Admin\AgentCallDispositionsController::class, 'edit'])->name('agent-records.edit');
        Route::put('agent-records/{record}', [\App\Http\Controllers\Admin\AgentCallDispositionsController::class, 'update'])->name('agent-records.update');
        Route::post('agent-records/export', [\App\Http\Controllers\Admin\AgentCallDispositionsController::class, 'export'])->name('agent-records.export');
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

        // Leads integration (ViciDial-style lists + leads)
        Route::prefix('leads')->name('leads.')->group(function () {
            // Lead lists
            Route::get('lists', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'index'])->name('lists.index');
            Route::post('lists', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'store'])->name('lists.store');
            Route::get('lists/{list}', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'show'])->name('lists.show');
            Route::put('lists/{list}', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'update'])->name('lists.update');
            Route::post('lists/{list}/toggle', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'toggle'])->name('lists.toggle');
            Route::post('lists/{list}/load-hopper', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'loadHopper'])->name('lists.load-hopper');
            Route::post('lists/delete', [\App\Http\Controllers\Admin\Leads\LeadListsController::class, 'destroy'])->name('lists.destroy');

            // Leads (scoped by list)
            Route::get('lists/{list}/leads', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'index'])->name('leads.index');
            Route::get('lists/{list}/leads/create', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'create'])->name('leads.create');
            Route::post('lists/{list}/leads', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'store'])->name('leads.store');
            Route::get('lists/{list}/leads/{lead}/edit', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'edit'])->name('leads.edit');
            Route::put('lists/{list}/leads/{lead}', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'update'])->name('leads.update');
            Route::post('lists/{list}/leads/delete', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'destroy'])->name('leads.destroy');
            Route::post('lists/{list}/leads/bulk', [\App\Http\Controllers\Admin\Leads\LeadsController::class, 'bulk'])->name('leads.bulk');

            // Import wizard
            Route::get('lists/{list}/import', [\App\Http\Controllers\Admin\Leads\LeadImportController::class, 'form'])->name('import.form');
            Route::post('lists/{list}/import/upload', [\App\Http\Controllers\Admin\Leads\LeadImportController::class, 'upload'])->name('import.upload');
            Route::get('lists/{list}/import/mapping', [\App\Http\Controllers\Admin\Leads\LeadImportController::class, 'mapping'])->name('import.mapping');
            Route::post('lists/{list}/import/confirm', [\App\Http\Controllers\Admin\Leads\LeadImportController::class, 'confirm'])->name('import.confirm');
            Route::post('import/track/dismiss', [\App\Http\Controllers\Admin\Leads\LeadImportController::class, 'dismissTrack'])->name('import.track.dismiss');
            Route::get('lists/{list}/import/progress/{runId}', [\App\Http\Controllers\Admin\Leads\LeadImportProgressController::class, 'show'])
                ->name('import.progress')
                ->middleware('throttle:120,1');

            // Export
            Route::get('lists/{list}/export', [\App\Http\Controllers\Admin\Leads\LeadExportController::class, 'download'])->name('export.download');
            Route::get('lists/{list}/template', [\App\Http\Controllers\Admin\Leads\LeadExportController::class, 'template'])->name('export.template');
            Route::get('export/all', [\App\Http\Controllers\Admin\Leads\LeadExportController::class, 'downloadAll'])->name('export.all');
        });

        // Super Admin only
        Route::middleware('role:Super Admin')->group(function () {
            Route::get('configuration', [\App\Http\Controllers\Admin\ConfigurationController::class, 'index'])->name('configuration');
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
            Route::post('campaigns/delete', [\App\Http\Controllers\Admin\CampaignsController::class, 'destroy'])->name('campaigns.destroy');
            Route::get('forms', [\App\Http\Controllers\Admin\FormsController::class, 'index'])->name('forms.index');
            Route::post('forms', [\App\Http\Controllers\Admin\FormsController::class, 'store'])->name('forms.store');
            Route::put('forms/{form}', [\App\Http\Controllers\Admin\FormsController::class, 'update'])->name('forms.update');
            Route::post('forms/delete', [\App\Http\Controllers\Admin\FormsController::class, 'destroy'])->name('forms.destroy');
            Route::get('agent-screen', [\App\Http\Controllers\Admin\AgentScreenController::class, 'index'])->name('agent-screen.index');
            Route::post('agent-screen', [\App\Http\Controllers\Admin\AgentScreenController::class, 'store'])->name('agent-screen.store');
            Route::put('agent-screen/{field}', [\App\Http\Controllers\Admin\AgentScreenController::class, 'update'])->name('agent-screen.update');
            Route::post('agent-screen/delete', [\App\Http\Controllers\Admin\AgentScreenController::class, 'destroy'])->name('agent-screen.destroy');
            Route::get('attendance-statuses', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'index'])->name('attendance-statuses.index');
            Route::post('attendance-statuses', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'store'])->name('attendance-statuses.store');
            Route::put('attendance-statuses/{id}', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'update'])->name('attendance-statuses.update');
            Route::post('attendance-statuses/delete', [\App\Http\Controllers\Admin\AttendanceStatusTypesController::class, 'destroy'])->name('attendance-statuses.destroy');

            // Lead field schema (Super Admin)
            Route::prefix('leads')->name('leads.')->group(function () {
                Route::get('fields', [\App\Http\Controllers\Admin\Leads\LeadFieldsController::class, 'index'])->name('fields.index');
                Route::post('fields', [\App\Http\Controllers\Admin\Leads\LeadFieldsController::class, 'store'])->name('fields.store');
                Route::put('fields/{id}', [\App\Http\Controllers\Admin\Leads\LeadFieldsController::class, 'update'])->name('fields.update');
                Route::post('fields/delete', [\App\Http\Controllers\Admin\Leads\LeadFieldsController::class, 'destroy'])->name('fields.destroy');
            });
        });
    });
    Route::get('forms/{type}', [FormController::class, 'show'])->name('forms.show')->where('type', '[a-z_]+');
    Route::post('forms/submit', [FormController::class, 'store'])->name('forms.store')->middleware('throttle:form-submit');
});
