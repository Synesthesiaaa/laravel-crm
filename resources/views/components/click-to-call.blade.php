{{--
  Usage: <x-click-to-call />
  Floating click-to-call widget. Available for agents on any page.
  Connects to Alpine.js store 'call'.
--}}
<div class="phone-widget" x-data="clickToCall()" x-show="open || $store.call.state !== 'idle'" style="display: none;">
    {{-- Trigger fab --}}
    <button type="button"
            @click="open = !open"
            x-show="$store.call.state === 'idle'"
            class="w-12 h-12 rounded-full bg-[var(--color-primary)] text-white shadow-lg flex items-center justify-center hover:bg-[var(--color-primary-hover)] transition-all hover:scale-105 active:scale-95"
            aria-label="Open click-to-call">
        <x-icon name="phone" class="w-5 h-5" />
    </button>

    {{-- Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-90 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         class="phone-widget-panel mb-2"
         style="display: none;">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Quick Dial</h4>
            <button @click="open = false" class="btn-icon w-6 h-6">
                <x-icon name="x-mark" class="w-3.5 h-3.5" />
            </button>
        </div>
        <div class="form-field mb-3">
            <label class="form-label">Phone Number</label>
            <input type="tel"
                   x-model="phoneNumber"
                   @keydown.enter="dial()"
                   class="form-input"
                   placeholder="+63 XXX XXX XXXX"
                   x-ref="phoneInput"
                   x-init="$nextTick(() => open && $refs.phoneInput.focus())" />
        </div>
        <div class="flex gap-2">
            <button @click="dial()" class="btn-primary flex-1 text-sm" :disabled="!phoneNumber">
                <x-icon name="phone" class="w-4 h-4" />
                Dial
            </button>
            <button @click="open = false" class="btn-ghost text-sm">Cancel</button>
        </div>
    </div>
</div>
