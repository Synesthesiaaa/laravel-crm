<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">DTMF Keypad</h3>
    <div class="grid grid-cols-3 gap-2">
        <template x-for="digit in ['1','2','3','4','5','6','7','8','9','*','0','#']" :key="digit">
            <button class="btn-secondary text-sm py-2" @click="sendDtmf(digit)" x-text="digit"></button>
        </template>
    </div>
    <div class="flex gap-2">
        <input class="form-input" x-model="dtmf.custom" placeholder="Custom DTMF sequence" />
        <button class="btn-secondary text-xs" @click="sendDtmf(dtmf.custom)">Send</button>
    </div>
</div>
