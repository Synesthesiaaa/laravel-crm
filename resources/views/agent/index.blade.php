@extends('layouts.app')

@section('title', 'Agent Screen')
@section('header-icon')<x-icon name="speaker-wave" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Agent Screen')

@section('content')
<div x-data="agentScreen()" x-init="init()" data-campaign="{{ session('campaign', 'mbsales') }}" data-user-id="{{ auth()->id() }}" class="flex flex-col lg:flex-row gap-6 h-full">

    {{-- LEFT: Lead info + form --}}
    <div class="flex-1 min-w-0 space-y-4">

        {{-- Current lead card --}}
        <div class="md-card p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Lead Information</h3>
                <div class="flex items-center gap-2">
                    <template x-if="featureEnabled('predictive_dialing')">
                        <button type="button"
                                class="text-xs px-2 py-1 rounded-md border"
                                :class="predictiveMode ? 'border-emerald-500 text-emerald-600 bg-emerald-50' : 'border-[var(--color-border)] text-[var(--color-on-surface-dim)]'"
                                @click="togglePredictiveMode()">
                            <span x-text="predictiveMode ? 'Predictive: ON' : 'Predictive: OFF'">Predictive: OFF</span>
                        </button>
                    </template>
                    <button type="button" class="btn-secondary text-xs px-2 py-1" @click="fetchNextLead()" :disabled="callState !== 'idle' && callState !== 'wrapup' || loadingNextLead"
                            title="Get next lead">
                        <x-icon name="arrow-path" class="w-3.5 h-3.5" />
                        <span x-text="loadingNextLead ? 'Loading...' : 'Next Lead'">Next Lead</span>
                    </button>
                    <x-badge :dot="false" type="info" x-text="leadId ? 'Lead #' + leadId : 'No lead loaded'">No lead loaded</x-badge>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-field">
                    <label class="form-label">Phone Number</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="phoneNumber" class="form-input flex-1" placeholder="+63 XXX XXX XXXX" />
                        <button type="button" class="phone-dial-btn" @click="dial()" title="Call"
                                :disabled="callState !== 'idle'" :class="{ 'opacity-50 cursor-not-allowed': callState !== 'idle' }">
                            <x-icon name="phone" class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <div class="form-field">
                    <label class="form-label">Lead ID</label>
                    <input type="text" x-model="leadId" class="form-input" placeholder="ViciDial Lead ID" />
                </div>
                <div class="form-field">
                    <label class="form-label">Client Name</label>
                    <input type="text" x-model="clientName" class="form-input" placeholder="Client full name" />
                </div>
                <div class="form-field">
                    <label class="form-label">Campaign</label>
                    <input type="text" value="{{ session('campaign_name') }}" class="form-input" readonly />
                </div>
            </div>
        </div>

        {{-- Form data (dynamic fields from agent screen config) --}}
        @if(isset($fields) && $fields->isNotEmpty())
        <div class="md-card p-5">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-4">Capture Details</h3>
            <form id="capture-form" @submit.prevent="saveForm()" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($fields as $field)
                <div class="@if(($field->field_width ?? '') === 'full') sm:col-span-2 @endif">
                    @if($field->field_type === 'textarea')
                        <div class="form-field">
                            <label class="form-label">{{ $field->label }}@if($field->required)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif</label>
                            <textarea class="form-textarea" name="{{ $field->field_name }}" rows="3"
                                      @if($field->required) required @endif></textarea>
                        </div>
                    @elseif($field->field_type === 'select')
                        <div class="form-field">
                            <label class="form-label">{{ $field->label }}</label>
                            <select class="form-select" name="{{ $field->field_name }}" @if($field->required) required @endif>
                                <option value="">-- Select --</option>
                                @foreach($field->options_array ?? [] as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="form-field">
                            <label class="form-label">{{ $field->label }}@if($field->required)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif</label>
                            <input type="{{ $field->field_type ?? 'text' }}" class="form-input"
                                   name="{{ $field->field_name }}"
                                   @if($field->required) required @endif />
                        </div>
                    @endif
                </div>
                @endforeach
                <div class="sm:col-span-2 flex gap-3 pt-2">
                    <button type="submit" class="btn-primary" :disabled="saving">
                        <x-icon name="check" class="w-4 h-4" />
                        <span x-text="saving ? 'Saving...' : 'Save Record'">Save Record</span>
                    </button>
                    <button type="button" class="btn-ghost" @click="clearForm()">Clear</button>
                </div>
            </form>
        </div>
        @else
        <div class="md-card p-8 text-center">
            <x-icon name="computer-desktop" class="w-12 h-12 mx-auto mb-3 text-[var(--color-on-surface-dim)] opacity-40" />
            <p class="text-sm text-[var(--color-on-surface-muted)]">No agent screen fields configured.</p>
            @can('Super Admin')
            <a href="{{ route('admin.agent-screen.index') }}" class="link-primary text-xs mt-2 inline-block">Configure fields →</a>
            @endcan
        </div>
        @endif

        {{-- Activity timeline --}}
        <div class="md-card p-5">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-4">Recent Activity</h3>
            <div class="timeline" id="activity-timeline">
                <template x-if="recentCalls.length === 0">
                    <p class="text-sm text-[var(--color-on-surface-dim)]">No recent activity.</p>
                </template>
                <template x-for="entry in recentCalls" :key="entry.id">
                    <div class="timeline-item pb-3">
                        <div class="timeline-dot">
                            <x-icon name="phone" class="w-3 h-3 text-[var(--color-on-surface-muted)]" />
                        </div>
                        <div class="timeline-content">
                            <p class="text-sm font-medium text-[var(--color-on-surface)]" x-text="entry.phone"></p>
                            <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5" x-text="entry.time + ' · ' + entry.disposition"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- RIGHT: Call controls --}}
    <div class="lg:w-72 xl:w-80 shrink-0 space-y-4">

        {{-- Call status --}}
        <div class="md-card p-5">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-4">Call Controls</h3>

            <div class="text-center py-4">
                {{-- Status indicator --}}
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold mb-4"
                     :class="{
                         'call-connected': callState === 'connected',
                         'call-ringing':   callState === 'dialing' || callState === 'ringing',
                         'call-hold':      callState === 'hold',
                         'call-wrapup':    callState === 'wrapup',
                         'bg-[var(--color-surface-2)] text-[var(--color-on-surface-dim)] border border-[var(--color-border)]': callState === 'idle',
                     }">
                    <x-icon name="phone" class="w-3.5 h-3.5" />
                    <span x-show="callState === 'idle'">Ready</span>
                    <span x-show="callState === 'dialing'">Dialing...</span>
                    <span x-show="callState === 'ringing'">Ringing...</span>
                    <span x-show="callState === 'connected'" x-text="'Connected · ' + formatDuration(duration)"></span>
                    <span x-show="callState === 'hold'">On Hold</span>
                    <span x-show="callState === 'wrapup'">Wrap-up</span>
                </div>

                {{-- Phone number display --}}
                <div class="text-2xl font-bold text-[var(--color-on-surface)] mb-4 font-mono min-h-[2rem]"
                     x-text="phoneNumber || '—'"></div>

                {{-- Dial / Hangup buttons --}}
                <div class="flex items-center justify-center gap-4">
                    <button class="phone-dial-btn"
                            @click="dial()"
                            x-show="callState === 'idle'"
                            :disabled="!phoneNumber || dialBlocked">
                        <x-icon name="phone" class="w-6 h-6" />
                    </button>
                    <button class="phone-hangup-btn"
                            @click="hangup()"
                            x-show="callState !== 'idle' && callState !== 'wrapup'">
                        <x-icon name="phone-x-mark" class="w-6 h-6" />
                    </button>
                    <button class="btn-secondary text-sm px-3 py-2"
                            @click="toggleHold()"
                            x-show="callState === 'connected'">
                        <x-icon name="pause" class="w-4 h-4" />
                        Hold
                    </button>
                </div>
            </div>
        </div>

        {{-- Disposition --}}
        <div class="md-card p-5" x-show="callState === 'wrapup' || callState === 'idle'">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-3">Disposition</h3>
            <div class="form-field mb-3">
                <label class="form-label">Code</label>
                <select x-model="dispositionCode" class="form-select">
                    <option value="">-- Select disposition --</option>
                    @foreach($dispositionCodes ?? [] as $dc)
                        @php
                            $code = is_array($dc) ? ($dc['code'] ?? '') : ($dc->code ?? '');
                            $label = is_array($dc) ? ($dc['label'] ?? $code) : ($dc->label ?? $code);
                        @endphp
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field mb-3">
                <label class="form-label">Notes</label>
                <textarea x-model="dispositionNotes" class="form-textarea" rows="3" placeholder="Optional call notes..."></textarea>
            </div>
            <button class="btn-primary w-full" @click="saveDisposition()" :disabled="!dispositionCode || savingDisposition">
                <x-icon name="check" class="w-4 h-4" />
                <span x-text="savingDisposition ? 'Saving...' : 'Save Disposition'">Save Disposition</span>
            </button>
        </div>

        {{-- Mute / controls --}}
        <div class="md-card p-5" x-show="callState === 'connected' || callState === 'hold'">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-3">Audio Controls</h3>
            <div class="grid grid-cols-2 gap-2">
                <button class="btn-secondary text-sm" @click="toggleMute()" :class="muted ? 'btn-danger' : ''">
                    <x-icon name="microphone" class="w-4 h-4" />
                    <span x-text="muted ? 'Unmute' : 'Mute'">Mute</span>
                </button>
                <button class="btn-secondary text-sm" @click="toggleHold()" :class="{ 'btn-warning': callState === 'hold' }">
                    <x-icon name="pause" class="w-4 h-4" />
                    <span x-text="callState === 'hold' ? 'Resume' : 'Hold'">Hold</span>
                </button>
            </div>
        </div>

        @if(($telephonyFeatures['session_controls'] ?? true) === true)
            @include('agent.partials.session-panel')
        @endif
        @if(($telephonyFeatures['ingroup_management'] ?? true) === true)
            @include('agent.partials.ingroup-panel')
        @endif
        @if(($telephonyFeatures['transfer_controls'] ?? true) === true)
            @include('agent.partials.transfer-panel')
        @endif
        @if(($telephonyFeatures['recording_controls'] ?? true) === true)
            @include('agent.partials.recording-controls')
        @endif
        @if(($telephonyFeatures['dtmf_controls'] ?? true) === true)
            @include('agent.partials.dtmf-keypad')
        @endif
        @if(($telephonyFeatures['callback_controls'] ?? true) === true)
            @include('agent.partials.callback-form')
        @endif
        @if(($telephonyFeatures['lead_tools'] ?? true) === true)
            @include('agent.partials.lead-search')
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script>
window.agentScreen = function() {
    return {
        callState: 'idle',
        sessionId: null,
        phoneNumber: '',
        leadId: '',
        clientName: '',
        duration: 0,
        timer: null,
        muted: false,
        saving: false,
        savingDisposition: false,
        dispositionCode: '',
        dispositionNotes: '',
        recentCalls: [],
        dialBlocked: false,
        loadingNextLead: false,
        predictiveMode: false,
        predictiveDelay: 3,
        _predictiveTimer: null,
        vici: {
            loading: false,
            phone_login: '',
            phone_pass: '',
            pause_code: 'BREAK',
            pause_codes: @js(config('vicidial.pause_codes', ['BREAK'])),
            ingroups_raw: '',
            blended: true,
        },
        transfer: {
            phone_number: '',
            ingroup: '',
        },
        recording: {
            statusText: '',
        },
        dtmf: {
            custom: '',
        },
        callback: {
            datetime: '',
            type: 'ANYONE',
            user: '',
            comments: '',
        },
        leadTools: {
            phone_search: '',
            raw: '',
        },

        _echoUnsubscribe: null,
        _statusPollInterval: null,
        features: @js($telephonyFeatures ?? []),

        init() {
            this.$watch('callState', (v) => Alpine.store('call').state = v);
            this.$watch('$store.call.duration', (v) => { this.duration = v; });
            this.$watch('$store.call.state', (v) => { if (v) this.callState = v; });
            this.$watch('$store.call.number', (v) => { if (v) this.phoneNumber = v; });
            this.$watch('$store.call.sessionId', (v) => { if (v) this.sessionId = v; });
            if (!this.featureEnabled('predictive_dialing')) {
                this.predictiveMode = false;
            }
            this.syncCallStatus();
            if (this.featureEnabled('session_controls')) {
                this.syncVicidialStatus();
            }
            const te = window.TelephonyEcho;
            if (te && te.initEcho && te.isBroadcastEnabled()) {
                te.initEcho();
                const userId = parseInt(this.$el.dataset.userId, 10);
                if (userId) {
                    this._echoUnsubscribe = te.subscribeAgentChannel(userId, (p) => {
                        if (String(p.session_id) === String(this.sessionId)) {
                            if (['completed','failed','abandoned'].includes(p.to_status)) {
                                clearInterval(this.timer);
                                this.timer = null;
                                Alpine.store('call').stopTimer();
                                this.callState = 'wrapup';
                                Alpine.store('call').state = 'wrapup';
                                if (p.to_status === 'failed') {
                                    Alpine.store('toast').warning('Call failed or ended before answer.');
                                } else {
                                    Alpine.store('toast').info('Call ended. Please save disposition.');
                                }
                                if (this.predictiveMode && p.to_status === 'failed') {
                                    this.schedulePredictiveDial();
                                }
                            } else if (p.to_status === 'ringing') {
                                this.callState = 'ringing';
                                Alpine.store('call').state = 'ringing';
                                Alpine.store('toast').info('Call is ringing...');
                            } else if (['answered','in_call','on_hold'].includes(p.to_status)) {
                                this.callState = 'connected';
                                Alpine.store('call').state = 'connected';
                                Alpine.store('call').startTimer();
                                Alpine.store('toast').success('Call connected.');
                            }
                        }
                    });
                }
            } else {
                this._statusPollInterval = setInterval(() => this.syncCallStatus(), 15000);
            }
            if (this.featureEnabled('session_controls')) {
                setInterval(() => this.syncVicidialStatus(), 15000);
            }

            window.addEventListener('telephony-shortcut-dial', () => this.dial());
            window.addEventListener('telephony-shortcut-hangup', () => this.hangup());
            window.addEventListener('telephony-shortcut-transfer', () => {
                const panel = document.querySelector('[x-data=\"agentScreen()\"]');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            window.addEventListener('telephony-shortcut-recording', () => {
                if (!this.featureEnabled('recording_controls')) return;
                this.startRecording();
            });
            window.addEventListener('telephony-shortcut-pause', () => {
                if (!this.featureEnabled('session_controls')) return;
                if (Alpine.store('vicidial').status === 'paused') this.viciPause('RESUME');
                else this.viciPause('PAUSE');
            });
        },

        featureEnabled(key) {
            return !!this.features[key];
        },

        async syncCallStatus() {
            try {
                const res = await window.axios.get('/api/call/status');
                if (res.data.active && res.data.call) {
                    this.sessionId = res.data.call.session_id;
                    this.phoneNumber = res.data.call.phone_number || this.phoneNumber;
                    this.duration = res.data.call.duration_seconds || 0;
                    const statusMap = { dialing: 'dialing', ringing: 'ringing', answered: 'connected', in_call: 'connected', on_hold: 'hold' };
                    this.callState = statusMap[res.data.call.status] || 'connected';
                    Alpine.store('call').state = this.callState;
                    Alpine.store('call').number = this.phoneNumber;
                    Alpine.store('call').setSessionId(this.sessionId);
                    if (this.callState === 'connected') Alpine.store('call').startTimer();
                } else if (res.data.disposition_pending && res.data.pending_call) {
                    this.dialBlocked = true;
                    this.callState = 'wrapup';
                    this.sessionId = res.data.pending_call.session_id;
                    this.phoneNumber = res.data.pending_call.phone_number || this.phoneNumber;
                    Alpine.store('call').state = 'wrapup';
                } else {
                    this.dialBlocked = false;
                    this.callState = 'idle';
                    Alpine.store('call').state = 'idle';
                }
            } catch {}
        },

        async syncVicidialStatus() {
            try {
                const data = await Alpine.store('vicidial').sync(this.$el.dataset.campaign || 'mbsales');
                const raw = data?.agent_status?.data?.raw_response || '';
                if (!this.sessionId && typeof raw === 'string' && raw.includes('INCALL')) {
                    this.callState = 'connected';
                    Alpine.store('call').state = 'connected';
                    if (raw.includes('|')) {
                        const parts = raw.split('|');
                        if (parts[10]) {
                            this.phoneNumber = parts[10];
                            Alpine.store('call').number = parts[10];
                        }
                    }
                }
            } catch {}
        },

        async viciLogin() {
            if (!this.featureEnabled('session_controls')) return;
            this.vici.loading = true;
            try {
                await window.axios.post('/api/vicidial/session/login', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    phone_login: this.vici.phone_login || null,
                    phone_pass: this.vici.phone_pass || null,
                    blended: this.vici.blended,
                    ingroups: this.parseIngroups(this.vici.ingroups_raw),
                });
                Alpine.store('toast').success('VICIdial session initialized.');
                await this.syncVicidialStatus();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'VICIdial login failed.');
            } finally {
                this.vici.loading = false;
            }
        },

        async viciPause(value) {
            if (!this.featureEnabled('session_controls')) return;
            this.vici.loading = true;
            try {
                await window.axios.post('/api/vicidial/session/pause', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    value,
                });
                Alpine.store('toast').info(value === 'PAUSE' ? 'Agent paused.' : 'Agent resumed.');
                await this.syncVicidialStatus();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to change pause state.');
            } finally {
                this.vici.loading = false;
            }
        },

        async setPauseCode() {
            if (!this.featureEnabled('session_controls')) return;
            if (!this.vici.pause_code) return;
            try {
                await window.axios.post('/api/vicidial/session/pause-code', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    pause_code: this.vici.pause_code,
                });
                Alpine.store('toast').success('Pause code set.');
                await this.syncVicidialStatus();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to set pause code.');
            }
        },

        async viciLogout() {
            if (!this.featureEnabled('session_controls')) return;
            this.vici.loading = true;
            try {
                await window.axios.post('/api/vicidial/session/logout', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                });
                Alpine.store('toast').info('VICIdial session logged out.');
                await this.syncVicidialStatus();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'VICIdial logout failed.');
            } finally {
                this.vici.loading = false;
            }
        },

        async updateIngroups(action) {
            if (!this.featureEnabled('ingroup_management')) return;
            try {
                await window.axios.post('/api/vicidial/session/ingroups', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    action,
                    blended: this.vici.blended,
                    ingroups: this.parseIngroups(this.vici.ingroups_raw),
                });
                Alpine.store('toast').success('In-group settings updated.');
                await this.syncVicidialStatus();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to update in-groups.');
            }
        },

        parseIngroups(raw) {
            return (raw || '')
                .split(/[,\s]+/)
                .map(v => v.trim())
                .filter(Boolean);
        },

        async blindTransfer() {
            if (!this.featureEnabled('transfer_controls')) return;
            if (!this.transfer.phone_number) return;
            await this.transferAction('/api/call/transfer/blind', { phone_number: this.transfer.phone_number });
        },

        async warmTransfer() {
            if (!this.featureEnabled('transfer_controls')) return;
            await this.transferAction('/api/call/transfer/warm', {
                phone_number: this.transfer.phone_number || null,
                ingroup: this.transfer.ingroup || null,
                consultative: true,
            });
        },

        async localCloser() {
            if (!this.featureEnabled('transfer_controls')) return;
            if (!this.transfer.ingroup) return;
            await this.transferAction('/api/call/transfer/local', {
                ingroup: this.transfer.ingroup,
                phone_number: this.transfer.phone_number || null,
            });
        },

        async leaveThreeWay() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/leave-3way'); },
        async hangupXfer() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/hangup-xfer'); },
        async hangupBoth() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/hangup-both'); },
        async vmDrop() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/vm'); },
        async parkCustomer() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/park'); },
        async grabCustomer() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/grab'); },
        async parkIvr() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/park-ivr'); },
        async swapPark(target) { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/swap-park', { target }); },

        async transferAction(url, data = {}) {
            try {
                await window.axios.post(url, { campaign: this.$el.dataset.campaign || 'mbsales', ...data });
                Alpine.store('toast').success('Transfer action sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Transfer action failed.');
            }
        },

        async startRecording() {
            if (!this.featureEnabled('recording_controls')) return;
            try {
                const res = await window.axios.post('/api/call/recording/start', { campaign: this.$el.dataset.campaign || 'mbsales' });
                this.recording.statusText = res.data?.data?.raw_response || 'Recording started.';
                Alpine.store('toast').success('Recording start sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to start recording.');
            }
        },

        async stopRecording() {
            if (!this.featureEnabled('recording_controls')) return;
            try {
                const res = await window.axios.post('/api/call/recording/stop', { campaign: this.$el.dataset.campaign || 'mbsales' });
                this.recording.statusText = res.data?.data?.raw_response || 'Recording stopped.';
                Alpine.store('toast').info('Recording stop sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to stop recording.');
            }
        },

        async recordingStatus() {
            if (!this.featureEnabled('recording_controls')) return;
            try {
                const res = await window.axios.get('/api/call/recording/status', { params: { campaign: this.$el.dataset.campaign || 'mbsales' } });
                this.recording.statusText = res.data?.data?.raw_response || 'No status';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to fetch recording status.');
            }
        },

        async sendDtmf(digit) {
            if (!this.featureEnabled('dtmf_controls')) return;
            const digits = (digit || '').toString().trim();
            if (!digits) return;
            try {
                await window.axios.post('/api/call/dtmf', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    digits,
                });
                Alpine.store('toast').info('DTMF sent: ' + digits);
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to send DTMF.');
            }
        },

        async scheduleCallback() {
            if (!this.featureEnabled('callback_controls')) return;
            if (!this.leadId || !this.callback.datetime) return;
            try {
                await window.axios.post('/api/callbacks/schedule', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    lead_id: this.leadId,
                    callback_datetime: this.callback.datetime.replace('T', '+') + ':00',
                    callback_type: this.callback.type,
                    callback_user: this.callback.user || null,
                    callback_comments: this.callback.comments || null,
                });
                Alpine.store('toast').success('Callback scheduled.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to schedule callback.');
            }
        },

        async removeCallback() {
            if (!this.featureEnabled('callback_controls')) return;
            if (!this.leadId) return;
            try {
                await window.axios.post('/api/callbacks/remove', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    lead_id: this.leadId,
                });
                Alpine.store('toast').info('Callback removed.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to remove callback.');
            }
        },

        async callbackInfo() {
            if (!this.featureEnabled('callback_controls')) return;
            if (!this.leadId) return;
            try {
                const res = await window.axios.get('/api/callbacks/info', {
                    params: {
                        campaign: this.$el.dataset.campaign || 'mbsales',
                        lead_id: this.leadId,
                    },
                });
                this.leadTools.raw = res.data?.data?.raw_response || '';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to fetch callback info.');
            }
        },

        async searchLead() {
            if (!this.featureEnabled('lead_tools')) return;
            if (!this.leadTools.phone_search) return;
            try {
                const res = await window.axios.get('/api/leads/search', {
                    params: {
                        campaign: this.$el.dataset.campaign || 'mbsales',
                        phone_number: this.leadTools.phone_search,
                    },
                });
                this.leadTools.raw = res.data?.data?.raw_response || '';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Lead search failed.');
            }
        },

        async loadLeadInfo() {
            if (!this.featureEnabled('lead_tools')) return;
            try {
                const params = { campaign: this.$el.dataset.campaign || 'mbsales' };
                if (this.leadId) params.lead_id = this.leadId;
                if (!this.leadId && this.leadTools.phone_search) params.phone_number = this.leadTools.phone_search;
                const res = await window.axios.get('/api/leads/info', { params });
                this.leadTools.raw = res.data?.data?.raw_response || '';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to fetch lead info.');
            }
        },

        async switchLead() {
            if (!this.featureEnabled('lead_tools')) return;
            if (!this.leadId) return;
            try {
                const res = await window.axios.post('/api/leads/switch', {
                    campaign: this.$el.dataset.campaign || 'mbsales',
                    lead_id: this.leadId,
                });
                this.leadTools.raw = res.data?.data?.raw_response || '';
                Alpine.store('toast').success('Lead switch request sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Lead switch failed.');
            }
        },

        async dial() {
            if (!this.phoneNumber || this.callState !== 'idle' || this.dialBlocked) return;
            this.callState = 'dialing';
            Alpine.store('call').state = 'dialing';
            Alpine.store('call').number = this.phoneNumber;
            try {
                const campaign = this.$el.dataset.campaign || 'mbsales';
                const res = await window.axios.post('/api/call/dial?campaign=' + encodeURIComponent(campaign), {
                    phone_number: this.phoneNumber,
                    lead_id: this.leadId || null,
                });
                if (res.data.session_id) {
                    this.sessionId = res.data.session_id;
                    Alpine.store('call').setSessionId(res.data.session_id);
                }
                if (!res.data.success) {
                    this.callState = 'idle';
                    Alpine.store('call').state = 'idle';
                    Alpine.store('toast').error(res.data.message || 'Call failed.');
                    return;
                }
                // Wait for SIP.js state + AMI events to transition dialing -> ringing -> connected.
            } catch (e) {
                this.callState = 'idle';
                Alpine.store('call').state = 'idle';
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to originate call. Check connection.');
            }
        },

        async hangup() {
            await Alpine.store('call').hangupWebRTC();
            clearInterval(this.timer);
            this.timer = null;
            Alpine.store('call').stopTimer();
            this.callState = 'wrapup';
            Alpine.store('call').state = 'wrapup';
        },

        async toggleHold() {
            await Alpine.store('call').toggleHoldWebRTC();
            this.callState = Alpine.store('call').state;
        },

        toggleMute() {
            Alpine.store('call').toggleMuteWebRTC();
            this.muted = Alpine.store('call').muted;
        },

        formatDuration(s) {
            const m = String(Math.floor(s / 60)).padStart(2, '0');
            const sec = String(s % 60).padStart(2, '0');
            return `${m}:${sec}`;
        },

        async fetchNextLead() {
            if (this.loadingNextLead || (this.callState !== 'idle' && this.callState !== 'wrapup')) return;
            this.loadingNextLead = true;
            try {
                const campaign = this.$el.dataset.campaign || 'mbsales';
                const res = await window.axios.get('/api/leads/next', { params: { campaign } });
                if (res.data.lead) {
                    this.leadId = res.data.lead.lead_id || '';
                    this.phoneNumber = res.data.lead.phone_number || '';
                    this.clientName = res.data.lead.client_name || '';
                    Alpine.store('call').number = this.phoneNumber;
                    Alpine.store('toast').success('Lead loaded.');
                } else {
                    Alpine.store('toast').info(res.data.message || 'No leads available.');
                }
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to fetch lead.');
            } finally {
                this.loadingNextLead = false;
            }
        },

        async saveDisposition() {
            if (!this.dispositionCode) return;
            this.savingDisposition = true;
            const campaign = this.$el.dataset.campaign || 'mbsales';
            try {
                await window.axios.post('/api/disposition/save', {
                    campaign_code:    campaign,
                    call_session_id:  this.sessionId,
                    lead_id:          this.leadId,
                    phone_number:     this.phoneNumber,
                    disposition_code: this.dispositionCode,
                    notes:            this.dispositionNotes,
                    call_duration_seconds: this.duration,
                });
                this.recentCalls.unshift({
                    id:          Date.now(),
                    phone:       this.phoneNumber,
                    time:        new Date().toLocaleTimeString(),
                    disposition: this.dispositionCode,
                });
                if (this.recentCalls.length > 10) this.recentCalls.pop();
                Alpine.store('toast').success('Disposition saved.');
                this.callState = 'idle';
                this.sessionId = null;
                this.dispositionCode = '';
                this.dispositionNotes = '';
                this.duration = 0;
                this.dialBlocked = false;
                Alpine.store('call').state = 'idle';
                if (this.predictiveMode) {
                    this.schedulePredictiveDial();
                } else {
                    this.fetchNextLead();
                }
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to save disposition.');
            } finally {
                this.savingDisposition = false;
            }
        },

        togglePredictiveMode() {
            if (!this.featureEnabled('predictive_dialing')) return;
            this.predictiveMode = !this.predictiveMode;
            if (!this.predictiveMode && this._predictiveTimer) {
                clearTimeout(this._predictiveTimer);
                this._predictiveTimer = null;
            }
            Alpine.store('toast').info(this.predictiveMode ? 'Predictive dialing enabled.' : 'Predictive dialing disabled.');
            if (this.predictiveMode && this.callState === 'idle' && !this.dialBlocked) {
                this.schedulePredictiveDial();
            }
        },

        schedulePredictiveDial() {
            if (!this.featureEnabled('predictive_dialing')) return;
            if (!this.predictiveMode || this.callState !== 'idle') return;
            if (this._predictiveTimer) clearTimeout(this._predictiveTimer);
            this._predictiveTimer = setTimeout(() => this.predictiveDial(), Math.max(1, this.predictiveDelay) * 1000);
        },

        async predictiveDial() {
            if (!this.featureEnabled('predictive_dialing')) return;
            if (!this.predictiveMode || this.callState !== 'idle' || this.dialBlocked) return;
            try {
                const campaign = this.$el.dataset.campaign || 'mbsales';
                const res = await window.axios.post('/api/call/predictive-dial?campaign=' + encodeURIComponent(campaign));
                if (!res.data.success) {
                    Alpine.store('toast').warning(res.data.message || 'Predictive dial failed.');
                    return;
                }
                if (!res.data.lead) {
                    Alpine.store('toast').info(res.data.message || 'No leads available in hopper.');
                    return;
                }
                this.leadId = res.data.lead.lead_id || '';
                this.phoneNumber = res.data.lead.phone_number || '';
                this.clientName = res.data.lead.client_name || '';
                this.predictiveDelay = Number(res.data.predictive_delay_seconds || this.predictiveDelay || 3);
                this.sessionId = res.data.session_id || null;
                Alpine.store('call').number = this.phoneNumber;
                Alpine.store('call').setSessionId(this.sessionId);
                this.callState = 'dialing';
                Alpine.store('call').state = 'dialing';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Predictive dial request failed.');
            }
        },

        async saveForm() {
            this.saving = true;
            const form = document.getElementById('capture-form');
            if (!form) { this.saving = false; return; }
            const captureData = {};
            form.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.name && !el.name.startsWith('_')) captureData[el.name] = el.value ?? '';
            });
            try {
                await window.axios.post('/api/agent/capture', {
                    campaign_code: this.$el.dataset.campaign || 'mbsales',
                    call_session_id: this.sessionId,
                    lead_id: this.leadId,
                    phone_number: this.phoneNumber,
                    capture_data: captureData,
                });
                Alpine.store('toast').success('Record saved.');
                this.clearForm();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to save record.');
            } finally {
                this.saving = false;
            }
        },

        clearForm() {
            const form = document.getElementById('capture-form');
            if (form) {
                form.querySelectorAll('input, select, textarea').forEach(el => { el.value = ''; });
            }
        },
    };
};
</script>
@endpush
