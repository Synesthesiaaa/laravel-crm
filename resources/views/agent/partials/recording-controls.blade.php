<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Recording Controls</h3>
    <div class="flex gap-2">
        <button class="btn-secondary text-xs" @click="startRecording()">
            <x-icon name="play-circle" class="w-3.5 h-3.5" /> Start
        </button>
        <button class="btn-secondary text-xs" @click="stopRecording()">
            <x-icon name="stop-circle" class="w-3.5 h-3.5" /> Stop
        </button>
        <button class="btn-secondary text-xs" @click="recordingStatus()">
            <x-icon name="information-circle" class="w-3.5 h-3.5" /> Status
        </button>
    </div>
    <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="recording.statusText || 'No recording status yet.'"></p>
</div>
