            <div x-data="telephonyDiagnostics()" x-init="init()">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Telephony Readiness Check</h3>
                    <button type="button" class="btn-primary text-sm" @click="run()" {!! 'x-bind:disabled="loading"' !!}>
                        <span {!! 'x-bind:class="loading ? \'animate-spin\' : \'\'"' !!}><x-icon name="arrow-path" class="w-4 h-4" /></span>
                        <span x-text="loading ? 'Checking...' : 'Run Diagnostics'">Run Diagnostics</span>
                    </button>
                </div>

        <x-alert type="info" class="mb-4">
            Checks live connectivity to ViciDial APIs, AMI, and validates per-campaign and per-agent credential completeness.
            No data is modified.
        </x-alert>

        <template x-if="callUrlLinks.length > 0">
            <div class="mb-4 space-y-3">
                <x-alert type="info" title="Vicidial Call URL Links">
                    Paste these absolute URLs into the matching Vicidial campaign or in-group call URL fields.
                    The <code class="font-mono text-xs">VAR</code> prefix keeps Vicidial macro substitution enabled.
                </x-alert>

                <div class="space-y-3">
                    <template x-for="link in callUrlLinks" :key="link.key">
                        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[var(--color-on-surface)]" x-text="link.label"></p>
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1" x-text="link.vicidial_field"></p>
                                </div>
                                <button type="button"
                                        class="btn-secondary text-sm shrink-0"
                                        @click="copyLink(link)">
                                    <span x-text="copiedLinkKey === link.key ? 'Copied' : 'Copy URL'">Copy URL</span>
                                </button>
                            </div>

                            <div class="mt-3 rounded-md border border-dashed border-[var(--color-border)] bg-[var(--color-surface)] p-3">
                                <pre class="whitespace-pre-wrap break-all text-xs font-mono text-[var(--color-on-surface)]" x-text="link.url"></pre>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="checks.length === 0 && !loading">
            <p class="text-sm text-[var(--color-on-surface-dim)]">Click "Run Diagnostics" to test your ViciDial connection.</p>
        </template>

                <template x-if="loading && checks.length === 0">
                    <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-dim)]">
                        <x-icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        Running checks...
                    </div>
                </template>

                <template x-if="checks.length > 0">
                    <div class="space-y-2">
                        <template x-for="check in checks" :key="check.label">
                            <div class="flex items-start gap-3 rounded-lg border p-3"
                                 :class="{
                                     'border-green-500/40 bg-green-500/5':  check.status === 'ok',
                                     'border-amber-500/40 bg-amber-500/5':  check.status === 'warn',
                                     'border-red-500/40 bg-red-500/5':      check.status === 'fail',
                                 }">
                                <div class="shrink-0 mt-0.5">
                                    <template x-if="check.status === 'ok'">
                                        <x-icon name="check-circle" class="w-5 h-5 text-green-500" />
                                    </template>
                                    <template x-if="check.status === 'warn'">
                                        <x-icon name="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                                    </template>
                                    <template x-if="check.status === 'fail'">
                                        <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[var(--color-on-surface)]" x-text="check.label"></p>
                                    <p class="text-xs text-[var(--color-on-surface-muted)] mt-0.5 break-all" x-text="check.message"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
