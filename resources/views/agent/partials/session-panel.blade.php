<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">VICIdial Session</h3>
    <div class="grid grid-cols-2 gap-2">
        <div class="form-field col-span-2">
            <label class="form-label">Phone Login</label>
            <input class="form-input" x-model="vici.phone_login" placeholder="e.g. 350a" />
        </div>
        <div class="form-field col-span-2">
            <label class="form-label">Phone Pass</label>
            <input type="password" class="form-input" x-model="vici.phone_pass" placeholder="optional" />
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <button class="btn-primary text-xs" @click="viciLogin()" :disabled="vici.loading">
            <x-icon name="power" class="w-3.5 h-3.5" /> Login
        </button>
        <button class="btn-secondary text-xs" @click="viciPause('PAUSE')" :disabled="vici.loading || !$store.vicidial.loggedIn">
            <x-icon name="pause" class="w-3.5 h-3.5" /> Pause
        </button>
        <button class="btn-secondary text-xs" @click="viciPause('RESUME')" :disabled="vici.loading || !$store.vicidial.loggedIn">
            <x-icon name="play" class="w-3.5 h-3.5" /> Resume
        </button>
        <button class="btn-ghost text-xs" @click="viciLogout()" :disabled="vici.loading || !$store.vicidial.loggedIn">
            <x-icon name="arrow-right-on-rectangle" class="w-3.5 h-3.5" /> Logout
        </button>
    </div>
    <div class="form-field">
        <label class="form-label">Pause Code</label>
        <div class="flex gap-2">
            <select class="form-select" x-model="vici.pause_code">
                <template x-for="code in vici.pause_codes" :key="code">
                    <option :value="code" x-text="code"></option>
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
