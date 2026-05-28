<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Transfer & Conference</h3>
    <div class="grid grid-cols-1 gap-2">
        <input class="form-input" x-model="transfer.phone_number" placeholder="Transfer number" />
        <input class="form-input" x-model="transfer.ingroup" placeholder="In-group for local closer" />
    </div>
    <div class="grid grid-cols-2 gap-2">
        <button class="btn-secondary text-xs" @click="blindTransfer()" :disabled="busy.transfer">Blind</button>
        <button class="btn-secondary text-xs" @click="warmTransfer()" :disabled="busy.transfer">Warm</button>
        <button class="btn-secondary text-xs" @click="localCloser()" :disabled="busy.transfer">Local Closer</button>
        <button class="btn-secondary text-xs" @click="leaveThreeWay()" :disabled="busy.transfer">Leave 3-Way</button>
        <button class="btn-secondary text-xs" @click="hangupXfer()" :disabled="busy.transfer">Hangup Xfer</button>
        <button class="btn-secondary text-xs" @click="hangupBoth()" :disabled="busy.transfer">Hangup Both</button>
        <button class="btn-secondary text-xs" @click="parkCustomer()" :disabled="busy.transfer">Park</button>
        <button class="btn-secondary text-xs" @click="grabCustomer()" :disabled="busy.transfer">Grab</button>
        <button class="btn-secondary text-xs" @click="parkIvr()" :disabled="busy.transfer">Park IVR</button>
        <button class="btn-secondary text-xs" @click="swapPark('CUSTOMER')" :disabled="busy.transfer">Swap Cust</button>
        <button class="btn-secondary text-xs" @click="swapPark('XFER')" :disabled="busy.transfer">Swap Xfer</button>
        <button class="btn-secondary text-xs" @click="vmDrop()" :disabled="busy.transfer">VM Drop</button>
    </div>
    <div x-show="busy.transfer" class="flex items-center gap-1.5 text-xs text-[var(--color-on-surface-dim)]">
        <x-icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
        Sending transfer command...
    </div>
</div>
