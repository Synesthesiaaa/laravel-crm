<div x-data="agentScreen()" x-init="init()" data-campaign="{{ session('campaign', 'mbsales') }}" data-user-id="{{ auth()->id() }}" class="flex flex-col lg:flex-row gap-6 h-full">

    {{-- WebSocket health banner --}}
    <div x-show="$store.ws.isDisconnected && !$store.ws.dismissed"
         x-transition.opacity
         class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center gap-3 px-4 py-2 text-sm font-medium text-amber-900 bg-amber-100 border-b border-amber-300 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
        </svg>
        <span>Real-time updates unavailable &mdash; reconnecting<span class="animate-pulse">...</span></span>
        <button @click="$store.ws.dismiss()" class="ml-2 text-amber-700 hover:text-amber-900 underline text-xs">Dismiss</button>
    </div>
    {{-- Reset dismissed when connection restored --}}
    <template x-effect="if ($store.ws.isConnected) $store.ws.dismissed = false"></template>

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
                                :disabled="callState !== 'idle' || dialBlocked || !phoneNumber">
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
                            <input type="{{ ($field->field_type ?? 'text') === 'number' ? 'text' : ($field->field_type ?? 'text') }}" class="form-input"
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
                            :disabled="!phoneNumber || dialBlocked"
                            :title="!phoneNumber ? 'Enter a phone number first' : dialBlocked ? 'Complete disposition before dialing' : 'Click to dial'">
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
        <div class="md-card p-5" x-show="callState === 'wrapup'">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Disposition</h3>
                {{-- Show dismiss only when there is an error so agent is never stuck --}}
                <button type="button"
                        class="btn-ghost text-xs text-[var(--color-danger)]"
                        x-show="dispositionError"
                        @click="dismissDisposition()"
                        title="Dismiss and return to idle">
                    <x-icon name="x-mark" class="w-3.5 h-3.5" />
                    Dismiss
                </button>
            </div>

            {{-- Error banner with retry hint --}}
            <div x-show="dispositionError" class="mb-3 rounded-md border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/5 px-3 py-2">
                <p class="text-xs text-[var(--color-danger)]" x-text="dispositionError"></p>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Select a code and retry, or click Dismiss to return to idle.</p>
            </div>

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
                <span x-text="savingDisposition ? 'Saving...' : (dispositionError ? 'Retry Save' : 'Save Disposition')">Save Disposition</span>
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
