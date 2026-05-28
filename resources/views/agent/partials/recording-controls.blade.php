<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Recording Controls</h3>
    <div class="flex gap-2">
        <button class="btn-secondary text-xs" @click="startRecording()" :disabled="busy.recording">
            <x-icon name="play-circle" class="w-3.5 h-3.5" x-bind:class="busy.recording === 'start' ? 'animate-spin' : ''" />
            <span x-text="busy.recording === 'start' ? 'Starting...' : 'Start'">Start</span>
        </button>
        <button class="btn-secondary text-xs" @click="stopRecording()" :disabled="busy.recording">
            <x-icon name="stop-circle" class="w-3.5 h-3.5" x-bind:class="busy.recording === 'stop' ? 'animate-spin' : ''" />
            <span x-text="busy.recording === 'stop' ? 'Stopping...' : 'Stop'">Stop</span>
        </button>
        <button class="btn-secondary text-xs" @click="recordingStatus()" :disabled="busy.recording">
            <x-icon name="information-circle" class="w-3.5 h-3.5" x-bind:class="busy.recording === 'status' ? 'animate-spin' : ''" />
            <span x-text="busy.recording === 'status' ? 'Checking...' : 'Status'">Status</span>
        </button>
    </div>
    <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="recording.statusText || 'No recording status yet.'"></p>
</div>
