<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Transfer & Conference</h3>
    <div class="grid grid-cols-1 gap-2">
        <input class="form-input" x-model="transfer.phone_number" placeholder="Transfer number" />
        <input class="form-input" x-model="transfer.ingroup" placeholder="In-group for local closer" />
    </div>
    <div class="grid grid-cols-2 gap-2">
        <button class="btn-secondary text-xs" @click="blindTransfer()">Blind</button>
        <button class="btn-secondary text-xs" @click="warmTransfer()">Warm</button>
        <button class="btn-secondary text-xs" @click="localCloser()">Local Closer</button>
        <button class="btn-secondary text-xs" @click="leaveThreeWay()">Leave 3-Way</button>
        <button class="btn-secondary text-xs" @click="hangupXfer()">Hangup Xfer</button>
        <button class="btn-secondary text-xs" @click="hangupBoth()">Hangup Both</button>
        <button class="btn-secondary text-xs" @click="parkCustomer()">Park</button>
        <button class="btn-secondary text-xs" @click="grabCustomer()">Grab</button>
        <button class="btn-secondary text-xs" @click="parkIvr()">Park IVR</button>
        <button class="btn-secondary text-xs" @click="swapPark('CUSTOMER')">Swap Cust</button>
        <button class="btn-secondary text-xs" @click="swapPark('XFER')">Swap Xfer</button>
        <button class="btn-secondary text-xs" @click="vmDrop()">VM Drop</button>
    </div>
</div>
