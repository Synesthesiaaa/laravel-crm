<div x-data="supervisorDashboard()" x-init="init()" class="space-y-6">

    <x-page-header title="Supervisor Dashboard" description="Real-time agent monitoring."
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Supervisor' => null]" />

    {{-- Wallboard KPIs --}}
    <div class="wallboard animate-stagger">
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.agentsOnline">—</div>
            <div class="wallboard-label">Agents Online</div>
        </div>
        <div class="wallboard-metric" :class="{ 'wallboard-alert': stats.callsWaiting > 5 }">
            <div class="wallboard-value" x-text="stats.callsWaiting">—</div>
            <div class="wallboard-label">Calls Waiting</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.callsActive">—</div>
            <div class="wallboard-label">Active Calls</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.avgWaitTime">—</div>
            <div class="wallboard-label">Avg Wait (s)</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.todayTotal">—</div>
            <div class="wallboard-label">Today's Calls</div>
        </div>
        <div class="wallboard-metric">
            <div class="wallboard-value" x-text="stats.slaPercent + '%'">—</div>
            <div class="wallboard-label">SLA %</div>
        </div>
    </div>

    <div class="md-card p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <div class="form-field">
                <label class="form-label">Recipient Type</label>
                <select class="form-select" x-model="notification.recipient_type">
                    <option value="USER">USER</option>
                    <option value="USER_GROUP">USER_GROUP</option>
                    <option value="CAMPAIGN">CAMPAIGN</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">Recipient</label>
                <input class="form-input" x-model="notification.recipient" placeholder="e.g. AGENTS or TESTCAMP or 6666" />
            </div>
            <div class="form-field md:col-span-2">
                <label class="form-label">Message</label>
                <input class="form-input" x-model="notification.text" placeholder="Notification text" />
            </div>
            <div class="form-field">
                <label class="inline-flex items-center gap-2 text-xs text-[var(--color-on-surface-muted)] mb-1">
                    <input type="checkbox" x-model="notification.confetti" />
                    Confetti
                </label>
                <button class="btn-secondary w-full text-xs" @click="sendNotification()">Send</button>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-[var(--color-border)]" role="tablist">
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'agents' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'agents'" role="tab">
            Agent Status Grid
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'performance' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'performance'" role="tab">
            Performance Metrics
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'wallboard' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)] hover:text-[var(--color-on-surface)]'"
                @click="tab = 'wallboard'" role="tab">
            Live Wallboard
        </button>
    </div>

    {{-- Agent Status Grid --}}
    <div x-show="tab === 'agents'" role="tabpanel">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">
                Agent Status — <span x-text="agents.length + ' agents'" class="text-[var(--color-primary)]"></span>
            </h3>
            <button @click="refresh()" class="btn-secondary text-xs">
                <span class="inline-flex" :class="loading ? 'animate-spin' : ''">
                    <x-icon name="arrow-path" class="w-3.5 h-3.5" />
                </span>
                Refresh
            </button>
        </div>
        <template x-if="loading && agents.length === 0">
            <div class="agent-status-grid">
                <template x-for="i in 6" :key="i">
                    <div class="agent-card">
                        <div class="skeleton skeleton-text w-24"></div>
                        <div class="skeleton skeleton-text w-16 mt-2"></div>
                        <div class="skeleton skeleton-text w-20 mt-1"></div>
                    </div>
                </template>
            </div>
        </template>
        <div class="agent-status-grid">
            <template x-for="agent in agents" :key="agent.id">
                <div class="agent-card"
                     :class="{
                         'agent-card-available': agent.status === 'available',
                         'agent-card-oncall':    agent.status === 'oncall',
                         'agent-card-break':     agent.status === 'break',
                         'agent-card-wrapup':    agent.status === 'wrapup',
                     }">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-sm text-[var(--color-on-surface)] truncate" x-text="agent.name"></span>
                        <span class="badge text-xs"
                              :class="{
                                  'badge-active':   agent.status === 'available',
                                  'badge-error':    agent.status === 'oncall',
                                  'badge-warning':  agent.status === 'break',
                                  'badge-pending':  agent.status === 'wrapup',
                                  'badge-inactive': agent.status === 'offline',
                              }"
                              x-text="agent.status_label">
                        </span>
                    </div>
                    <p class="text-xs text-[var(--color-on-surface-dim)]" x-text="agent.current_call || '—'"></p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="agent.calls_today + ' calls today'"></span>
                        <span class="text-xs font-mono text-[var(--color-on-surface-muted)]" x-text="agent.since"></span>
                    </div>
                    <div class="text-[11px] text-[var(--color-on-surface-dim)] mt-1" x-text="'Vici: ' + (agent.vici_status || 'unknown') + ' · Queue: ' + (agent.queue_count ?? 0)"></div>
                    {{-- Supervisor controls --}}
                    <div class="flex gap-1.5 mt-2 flex-wrap">
                        <button class="btn-ghost text-xs px-2 py-1" @click="monitorAgent(agent)" title="Monitor (listen only)">
                            <x-icon name="eye" class="w-3 h-3" />
                            Monitor
                        </button>
                        <button class="btn-ghost text-xs px-2 py-1" @click="whisperAgent(agent)" title="Whisper (agent only)">
                            <x-icon name="microphone" class="w-3 h-3" />
                            Whisper
                        </button>
                        <button class="btn-ghost text-xs px-2 py-1" @click="forcePause(agent)" title="Force pause agent">
                            <x-icon name="pause" class="w-3 h-3" />
                            Pause
                        </button>
                        <button class="btn-ghost text-xs px-2 py-1" @click="forceLogout(agent)" title="Force logout agent">
                            <x-icon name="arrow-right-on-rectangle" class="w-3 h-3" />
                            Logout
                        </button>
                    </div>
                </div>
            </template>
        </div>
        <template x-if="!loading && agents.length === 0">
            <div class="table-empty py-12">
                <x-icon name="users" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm font-medium">No agents currently online.</p>
            </div>
        </template>
    </div>

    {{-- Performance Metrics --}}
    <div x-show="tab === 'performance'" role="tabpanel">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="chart-container">
                <p class="chart-title">Agent Performance — Today</p>
                <div id="chart-agent-perf" style="min-height: 260px;"></div>
            </div>
            <div class="chart-container">
                <p class="chart-title">Call Volume — Hourly</p>
                <div id="chart-hourly" style="min-height: 260px;"></div>
            </div>
        </div>
        <div class="mt-6">
            <x-table.index caption="Agent performance table">
                <x-table.head :columns="[
                    ['label' => 'Agent'],
                    ['label' => 'Status'],
                    ['label' => 'Calls Today', 'align' => 'right'],
                    ['label' => 'Avg Handle (s)', 'align' => 'right'],
                    ['label' => 'Dispositions', 'align' => 'right'],
                    ['label' => 'Since'],
                ]" />
                <tbody>
                    <template x-for="agent in agents" :key="agent.id">
                        <tr>
                            <td x-text="agent.name" class="font-medium"></td>
                            <td>
                                <span class="badge"
                                      :class="{
                                          'badge-active':   agent.status === 'available',
                                          'badge-error':    agent.status === 'oncall',
                                          'badge-warning':  agent.status === 'break',
                                          'badge-inactive': agent.status === 'offline',
                                      }"
                                      x-text="agent.status_label"></span>
                            </td>
                            <td class="text-right font-semibold" x-text="agent.calls_today"></td>
                            <td class="text-right font-mono text-sm" x-text="agent.avg_handle + 's'"></td>
                            <td class="text-right" x-text="agent.dispositions"></td>
                            <td class="text-[var(--color-on-surface-dim)] text-sm" x-text="agent.since"></td>
                        </tr>
                    </template>
                </tbody>
            </x-table.index>
        </div>
    </div>

    {{-- Live Wallboard --}}
    <div x-show="tab === 'wallboard'" role="tabpanel">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6 animate-stagger">
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.agentsOnline">0</div>
                <div class="wallboard-label">Agents Online</div>
            </div>
            <div class="wallboard-metric" :class="{ 'wallboard-alert': stats.callsWaiting > 5 }">
                <div class="wallboard-value text-4xl" x-text="stats.callsWaiting">0</div>
                <div class="wallboard-label">In Queue</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.callsActive">0</div>
                <div class="wallboard-label">On Call</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.todayTotal">0</div>
                <div class="wallboard-label">Total Today</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.avgWaitTime + 's'">0s</div>
                <div class="wallboard-label">Avg Wait</div>
            </div>
            <div class="wallboard-metric">
                <div class="wallboard-value text-4xl" x-text="stats.slaPercent + '%'">0%</div>
                <div class="wallboard-label">SLA</div>
            </div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Real-time Call Volume</p>
            <div id="chart-realtime" style="min-height: 200px;"></div>
        </div>
    </div>

</div>
