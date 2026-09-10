{{--
  Usage: <x-click-to-call />
  Floating click-to-call widget. Available for agents on any page.
  Connects to Alpine.js store 'call'.
--}}
<div class="phone-widget fab-stack-0" x-data="clickToCall()" x-show="open || $store.call.state !== 'idle'" style="display: none;" @click.stop>
    {{-- Trigger fab (idle only) --}}
    <button type="button"
            @click="open = !open"
            x-show="$store.call.state === 'idle'"
            class="widget-launcher w-12 h-12 rounded-full bg-[var(--color-primary)] text-white flex items-center justify-center transition-all hover:bg-[var(--color-primary-hover)] hover:scale-105 active:scale-95"
            :aria-label="open ? 'Close Quick Dial' : 'Open Quick Dial'"
            title="Quick Dial">
        <x-icon name="phone" class="w-5 h-5" />
    </button>

    {{-- Active call: hangup button --}}
    <div x-show="$store.call.state !== 'idle' && !open"
         x-transition
         class="phone-widget-panel widget-call-status mb-2"
         role="status"
         aria-live="polite">
        <span class="widget-call-status-number" x-text="$store.call.number || 'Call in progress'"></span>
        <span x-show="$store.call.state === 'connected'" class="text-xs text-[var(--color-on-surface-dim)]" x-text="'· ' + $store.call.formattedDuration()"></span>
        <button type="button" @click="hangup()" class="btn-danger shrink-0 text-sm py-1 px-3 rounded-lg flex items-center gap-1" x-bind:disabled="hangingUp">
            <x-icon name="phone-x-mark" class="w-4 h-4" x-bind:class="hangingUp ? 'animate-spin' : ''" />
            <span x-text="hangingUp ? 'Ending...' : 'Hang up'">Hang up</span>
        </button>
    </div>

    {{-- Dial panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-90 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         class="phone-widget-panel widget-call-panel mb-2"
         style="display: none;">
        <div class="widget-panel-header">
            <div>
                <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Quick Dial</h4>
            </div>
            <button type="button" @click="open = false" class="btn-icon widget-panel-close" aria-label="Close Quick Dial" title="Close Quick Dial">
                <x-icon name="x-mark" class="w-3.5 h-3.5" />
            </button>
        </div>
        <div class="form-field mb-3">
            <label for="quick-dial-phone-number" class="form-label">Phone Number</label>
            <input type="tel"
                   id="quick-dial-phone-number"
                   x-model="phoneNumber"
                   @keydown.enter="dial()"
                   class="form-input"
                   placeholder="+63 XXX XXX XXXX"
                   x-bind:disabled="dialing"
                   x-ref="phoneInput"
                   x-init="$nextTick(() => open && $refs.phoneInput.focus())" />
        </div>
        <div class="flex gap-2">
            <button type="button" @click="dial()" class="btn-primary flex-1 text-sm" x-bind:disabled="!phoneNumber || dialing">
                <x-icon name="phone" class="w-4 h-4" x-bind:class="dialing ? 'animate-spin' : ''" />
                <span x-text="dialing ? 'Dialing...' : 'Dial'">Dial</span>
            </button>
            <button type="button" @click="open = false" class="btn-ghost text-sm" x-bind:disabled="dialing">Cancel</button>
        </div>
    </div>
</div>
