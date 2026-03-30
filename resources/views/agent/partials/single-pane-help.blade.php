{{-- Story 1.2: In-app single-pane workflow — CRM-only steps; no second-tab instructions (FR1). --}}
@php
    $paneHelpStorageKey = 'agent_single_pane_help_collapsed_' . auth()->id();
@endphp
<div class="md-card p-4 border border-[var(--color-border)] bg-[var(--color-surface-elevated)]"
     x-data="{
         open: (() => { try { return localStorage.getItem('{{ $paneHelpStorageKey }}') !== '1'; } catch (e) { return true; } })(),
         toggle() {
             this.open = !this.open;
             try { localStorage.setItem('{{ $paneHelpStorageKey }}', this.open ? '0' : '1'); } catch (e) {}
         },
     }">
    <button type="button"
            class="flex w-full items-center justify-between gap-2 text-left rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
            @click="toggle()"
            x-bind:aria-expanded="open"
            aria-controls="single-pane-help-panel">
        <span class="text-sm font-semibold text-[var(--color-on-surface)]">Outbound workflow (single CRM tab)</span>
        <x-icon name="chevron-down" class="w-4 h-4 shrink-0 text-[var(--color-on-surface-dim)] transition-transform duration-200"
                x-bind:class="{ 'rotate-180': open }" />
    </button>
    <div id="single-pane-help-panel"
         x-show="open"
         x-collapse
         class="mt-3 text-xs text-[var(--color-on-surface-muted)] leading-relaxed">
        <ol class="list-decimal list-inside space-y-1.5 text-[var(--color-on-surface)]">
            <li>Use <strong class="font-medium">VICIdial Session</strong> (this page, right column) to log in, pause/resume, set pause codes, and log out.</li>
            <li>Dial from the lead card or use <strong class="font-medium">Next Lead</strong> when your campaign supports it.</li>
            <li>Use <strong class="font-medium">Call Controls</strong> to hang up, hold, or mute while on a call.</li>
            <li>Complete <strong class="font-medium">Disposition</strong> after the call before your next dial when required.</li>
        </ol>
        <p class="mt-3 pt-3 border-t border-[var(--color-border)] text-[var(--color-on-surface-dim)]">
            Do your routine work in <strong class="text-[var(--color-on-surface)]">this CRM tab only</strong>. Do not open VICIdial in a second browser tab for login, dialing, or wrap-up. A small hidden frame may run in the background for session binding—you do not interact with it.
        </p>
        <div class="mt-3 pt-3 border-t border-[var(--color-border)]">
            <p class="text-[var(--color-on-surface)] font-medium mb-1">Keyboard</p>
            <ul class="list-disc list-inside space-y-1 text-[var(--color-on-surface-dim)]">
                <li><kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">Ctrl</kbd> or <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">⌘</kbd> + <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">D</kbd> dial · <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">H</kbd> hangup · <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">T</kbd> transfer · <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">R</kbd> recording · <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">P</kbd> pause/resume (when session controls are on). Shortcuts are disabled while typing in a field.</li>
                <li><kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">Esc</kbd> dismisses the connection warning banner when it appears.</li>
                <li>During <strong class="font-medium text-[var(--color-on-surface)]">wrap-up</strong>, use <strong class="font-medium text-[var(--color-on-surface)]">Go to disposition</strong> in Call Controls (or tab to the Disposition panel) to reach the code field; complete disposition before your next dial when required. No new dialogs — same <kbd class="px-1 rounded bg-[var(--color-surface-3)] border border-[var(--color-border)] text-[var(--color-on-surface)]">Esc</kbd> behavior as above.</li>
            </ul>
        </div>
    </div>
</div>
