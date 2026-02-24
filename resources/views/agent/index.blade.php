@extends('layouts.app')

@section('title', 'Agent Screen')
@section('header-icon')<x-icon name="speaker-wave" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Agent Screen')

@section('content')
<div x-data="agentScreen()" x-init="init()" class="flex flex-col lg:flex-row gap-6 h-full">

    {{-- LEFT: Lead info + form --}}
    <div class="flex-1 min-w-0 space-y-4">

        {{-- Current lead card --}}
        <div class="md-card p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Lead Information</h3>
                <x-badge :dot="false" type="info" x-text="leadId ? 'Lead #' + leadId : 'No lead loaded'">No lead loaded</x-badge>
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
            <form @submit.prevent="saveForm()" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                         'call-ringing':   callState === 'ringing',
                         'call-hold':      callState === 'hold',
                         'call-wrapup':    callState === 'wrapup',
                         'bg-[var(--color-surface-2)] text-[var(--color-on-surface-dim)] border border-[var(--color-border)]': callState === 'idle',
                     }">
                    <x-icon name="phone" class="w-3.5 h-3.5" />
                    <span x-show="callState === 'idle'">Ready</span>
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
                            :disabled="!phoneNumber">
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
                        <option value="{{ $dc->code }}">{{ $dc->label }}</option>
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

    </div>
</div>
@endsection

@push('scripts')
<script>
window.agentScreen = function() {
    return {
        callState: 'idle',
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

        init() {
            // Sync with global call store
            this.$watch('callState', (v) => Alpine.store('call').state = v);
        },

        async dial() {
            if (!this.phoneNumber || this.callState !== 'idle') return;
            this.callState = 'ringing';
            Alpine.store('call').number = this.phoneNumber;
            try {
                await window.axios.get('/api/vicidial/proxy', {
                    params: { action: 'originate', phone: this.phoneNumber, lead_id: this.leadId }
                });
                this.callState = 'connected';
                Alpine.store('call').startTimer();
                this.timer = setInterval(() => this.duration++, 1000);
            } catch (e) {
                this.callState = 'idle';
                Alpine.store('toast').error('Failed to originate call. Check ViciDial connection.');
            }
        },

        hangup() {
            clearInterval(this.timer);
            Alpine.store('call').stopTimer();
            this.callState = 'wrapup';
        },

        toggleHold() {
            this.callState = this.callState === 'hold' ? 'connected' : 'hold';
        },

        toggleMute() {
            this.muted = !this.muted;
        },

        formatDuration(s) {
            const m = String(Math.floor(s / 60)).padStart(2, '0');
            const sec = String(s % 60).padStart(2, '0');
            return `${m}:${sec}`;
        },

        async saveDisposition() {
            if (!this.dispositionCode) return;
            this.savingDisposition = true;
            try {
                await window.axios.post('/api/disposition/save', {
                    lead_id:          this.leadId,
                    phone_number:     this.phoneNumber,
                    disposition_code: this.dispositionCode,
                    notes:            this.dispositionNotes,
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
                this.dispositionCode = '';
                this.dispositionNotes = '';
                this.duration = 0;
                Alpine.store('call').state = 'idle';
            } catch {
                Alpine.store('toast').error('Failed to save disposition.');
            } finally {
                this.savingDisposition = false;
            }
        },

        async saveForm() {
            this.saving = true;
            await new Promise(r => setTimeout(r, 400));
            Alpine.store('toast').success('Record saved.');
            this.saving = false;
        },

        clearForm() {
            document.querySelectorAll('#capture-form input, #capture-form select, #capture-form textarea')
                .forEach(el => { el.value = ''; });
        },
    };
};
</script>
@endpush
