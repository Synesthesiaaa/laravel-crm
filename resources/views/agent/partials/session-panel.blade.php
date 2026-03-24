<div class="md-card p-5 space-y-3">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">VICIdial Session</h3>
        {{-- Live phase badge --}}
        <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full border"
              :class="{
                  'border-emerald-400 text-emerald-600 bg-emerald-50':  vici.phase === 'ready',
                  'border-amber-400  text-amber-600  bg-amber-50':   ['requesting','iframe_loading','syncing'].includes(vici.phase),
                  'border-red-400    text-red-600    bg-red-50':     ['failed','timeout'].includes(vici.phase),
                  'border-[var(--color-border)] text-[var(--color-on-surface-dim)]': vici.phase === 'idle',
              }"
              x-text="{
                  idle:          'Offline',
                  requesting:    'Starting…',
                  iframe_loading:'Opening…',
                  syncing:       'Confirming…',
                  ready:         'Online',
                  failed:        'Failed',
                  timeout:       'Timed out',
              }[vici.phase] || vici.phase">
        </span>
    </div>

    <div class="grid grid-cols-2 gap-2">
        <div class="form-field col-span-2">
            <label class="form-label">
                Phone Login
                <span class="text-[var(--color-danger)] ml-0.5" title="Required">*</span>
            </label>
            <input class="form-input" x-model="vici.phone_login" placeholder="Your extension, e.g. 6001"
                   :disabled="['requesting','iframe_loading','syncing'].includes(vici.phase)" />
        </div>
        <div class="form-field col-span-2">
            <label class="form-label">Phone Pass <span class="text-[10px] text-[var(--color-on-surface-dim)]">(leave blank to use login)</span></label>
            <input type="password" class="form-input" x-model="vici.phone_pass" placeholder="SIP password"
                   :disabled="['requesting','iframe_loading','syncing'].includes(vici.phase)" />
        </div>
    </div>

    {{-- Phase-aware progress text --}}
    <div x-show="['requesting','iframe_loading','syncing'].includes(vici.phase)"
         class="flex items-center gap-1.5 text-[11px] text-amber-600">
        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span x-text="{
            requesting:    'Sending login request…',
            iframe_loading:'Loading VICIdial session page…',
            syncing:       'Waiting for VICIdial to confirm agent (' + vici._verifyPollCount + '/' + vici._verifyPollMax + ')…',
        }[vici.phase] || ''"></span>
    </div>

    <div x-show="vici.phase === 'failed' || vici.phase === 'timeout'"
         class="text-[11px] text-red-600 leading-snug">
        Login did not complete. Verify your Phone Login matches your VICIdial extension and retry.
    </div>

    <div class="flex flex-wrap gap-2">
        <button class="btn-primary text-xs" @click="viciLogin()"
                :disabled="['requesting','iframe_loading','syncing'].includes(vici.phase) || !vici.phone_login">
            <x-icon name="power" class="w-3.5 h-3.5" />
            <span x-text="['requesting','iframe_loading','syncing'].includes(vici.phase) ? 'Connecting…' : 'Login'">Login</span>
        </button>
        <button class="btn-secondary text-xs" @click="viciPause('PAUSE')"
                :disabled="vici.loading || !$store.vicidial.loggedIn">
            <x-icon name="pause" class="w-3.5 h-3.5" /> Pause
        </button>
        <button class="btn-secondary text-xs" @click="viciPause('RESUME')"
                :disabled="vici.loading || !$store.vicidial.loggedIn">
            <x-icon name="play" class="w-3.5 h-3.5" /> Resume
        </button>
        <button class="btn-ghost text-xs" @click="viciLogout()"
                :disabled="vici.loading || !$store.vicidial.loggedIn">
            <x-icon name="arrow-right-on-rectangle" class="w-3.5 h-3.5" /> Logout
        </button>
    </div>

    <div class="form-field">
        <label class="form-label">Pause Code</label>
        <div class="flex gap-2">
            <select class="form-select" x-model="vici.pause_code">
                <template x-for="item in vici.pause_codes" :key="item.code || item">
                    <option :value="item.code || item" x-text="item.label || item.code || item"></option>
                </template>
            </select>
            <button class="btn-secondary text-xs"
                    @click="setPauseCode()"
                    :disabled="!vici.pause_code || !$store.vicidial.loggedIn">
                Set
            </button>
        </div>
        <p class="text-[11px] text-[var(--color-on-surface-dim)]">
            If agent is not paused, the system will pause first and then apply code.
        </p>
    </div>
</div>
