@extends('layouts.app')

@section('title', 'Campaigns')
@section('header-icon')<x-icon name="building-office" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Campaigns')

@section('content')
<x-page-header title="Campaigns" description="Manage campaign settings."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Campaigns' => null]" />

<x-validation-errors />

{{-- Add campaign form --}}
<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Campaign</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.campaigns.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-form.input name="code" label="Code" :value="old('code')" required placeholder="e.g. SALES2024" />
                <x-form.input name="name" label="Name" :value="old('name')" required />
                <x-form.input name="description" label="Description" :value="old('description')" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <x-form.checkbox name="predictive_enabled" label="Predictive Dialing Enabled" :checked="old('predictive_enabled', false)" />
                <x-form.input name="predictive_delay_seconds" type="number" min="1" max="300" label="Predictive Delay (seconds)" :value="old('predictive_delay_seconds', 3)" />
                <x-form.input name="predictive_max_attempts" type="number" min="1" max="20" label="Max Attempts Per Lead" :value="old('predictive_max_attempts', 3)" />
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    Add Campaign
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Campaigns table --}}
<x-table.index caption="Campaigns list">
    <x-table.head :columns="[
        ['label' => 'Code'],
        ['label' => 'Name'],
        ['label' => 'Status'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    @forelse($campaigns as $c)
        <tbody x-data="{ editOpen: false }">
            <tr>
                <td><span class="font-mono font-semibold text-[var(--color-on-surface)] text-sm">{{ $c->code }}</span></td>
                <td>{{ $c->name }}</td>
                <td>
                    <x-badge :type="$c->is_active ? 'active' : 'inactive'">
                        {{ $c->is_active ? 'Active' : 'Inactive' }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                        </button>
                        <a href="{{ route('admin.forms.index', ['campaign' => $c->code]) }}" class="btn-ghost text-xs px-2 py-1">
                            <x-icon name="document-text" class="w-3.5 h-3.5" />
                            Forms
                        </a>
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('Deactivate campaign?', '{{ $c->name }} will be disabled.');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.campaigns.destroy') }}" x-ref="delFormC{{ $c->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $c->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="del($refs['delFormC{{ $c->id }}'])">
                                    <x-icon name="minus" class="w-3.5 h-3.5" />
                                    Deactivate
                                </button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            <tr x-show="editOpen" class="inline-edit-row" x-collapse>
                <td colspan="4">
                    <form method="POST" action="{{ route('admin.campaigns.update', $c) }}"
                          x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <x-form.input name="code" label="Code" :value="old('code', $c->code)" required />
                            <x-form.input name="name" label="Name" :value="old('name', $c->name)" required />
                            <x-form.input name="description" label="Description" :value="old('description', $c->description)" />
                            <x-form.input name="display_order" type="number" label="Display Order" :value="old('display_order', $c->display_order)" />
                            <x-form.input name="predictive_delay_seconds" type="number" min="1" max="300" label="Predictive Delay (seconds)" :value="old('predictive_delay_seconds', $c->predictive_delay_seconds ?? 3)" />
                            <x-form.input name="predictive_max_attempts" type="number" min="1" max="20" label="Max Attempts Per Lead" :value="old('predictive_max_attempts', $c->predictive_max_attempts ?? 3)" />
                        </div>
                        <div class="mt-3 flex items-center gap-4">
                            <x-form.checkbox name="is_active" label="Active" :checked="$c->is_active" />
                            <x-form.checkbox name="predictive_enabled" label="Predictive Enabled" :checked="$c->predictive_enabled" />
                            <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                <x-icon name="check" class="w-4 h-4" />
                                <span x-text="submitting ? 'Saving...' : 'Update'">Update</span>
                            </button>
                        </div>
                    </form>

                    @php($mappingScope = $mappingScopes->get($c->id, []))
                    @php($campaignServers = $vicidialServersByCampaign->get($c->code, collect()))
                    <div class="mt-6 border-t border-[var(--color-border)] pt-5"
                         x-data="campaignMappingEditor({
                            endpoint: @js(route('admin.campaigns.vicidial-campaigns', $c)),
                            selectedServerId: @js($mappingScope['server']['id'] ?? null),
                            mappings: @js($mappingScope['mappings'] ?? []),
                         })">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                            <div>
                                <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">VICIdial campaign mapping</h4>
                                <p class="mt-1 max-w-2xl text-xs text-[var(--color-on-surface-muted)]">
                                    Keep <span class="font-semibold">{{ $c->name }}</span> as the CRM scope while including one or more campaigns from its assigned VICIdial server.
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-[var(--color-surface-raised)] px-2.5 py-1 text-xs font-semibold text-[var(--color-on-surface-muted)]"
                                  aria-live="polite" x-text="selectionLabel">0 campaigns selected</span>
                        </div>

                        <form method="POST" action="{{ route('admin.campaigns.vicidial-mapping.update', $c) }}"
                              @submit="submitting = true">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,220px)_minmax(0,1fr)]">
                                <div class="form-field">
                                    <label class="form-label" for="mapping_server_{{ $c->id }}">VICIdial server</label>
                                    <select id="mapping_server_{{ $c->id }}" name="vicidial_server_id" class="form-select"
                                            x-model="selectedServerId" @change="selected = []; campaigns = []; loaded = false; loadCampaigns()" required>
                                        <option value="">Select a server</option>
                                        @foreach($campaignServers as $server)
                                            <option value="{{ $server->id }}">{{ $server->server_name }}</option>
                                        @endforeach
                                    </select>
                                    @if($campaignServers->isEmpty())
                                        <p class="form-help text-[var(--color-danger)]">Configure an active VICIdial server for this CRM campaign first.</p>
                                    @else
                                        <p class="form-help">Only servers already assigned to this CRM campaign are available.</p>
                                    @endif
                                </div>

                                <fieldset class="min-w-0" :disabled="!selectedServerId || loading">
                                    <legend class="form-label">VICIdial campaigns</legend>
                                    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" class="btn-ghost text-xs px-2 py-1" @click="loadCampaigns(true)" :disabled="!selectedServerId || loading">
                                                <x-icon name="arrow-path" class="w-3.5 h-3.5" />
                                                <span x-text="loaded ? 'Refresh campaigns' : 'Load campaigns'">Load campaigns</span>
                                            </button>
                                            <button type="button" class="btn-ghost text-xs px-2 py-1" @click="selectAll()" :disabled="!loaded || loading">Select all</button>
                                            <button type="button" class="btn-ghost text-xs px-2 py-1" @click="clearAll()" :disabled="!loaded || loading">Clear all</button>
                                            <label class="sr-only" for="mapping_search_{{ $c->id }}">Search VICIdial campaigns</label>
                                            <input id="mapping_search_{{ $c->id }}" type="search" class="form-input ml-auto min-w-[12rem] flex-1 text-sm"
                                                   placeholder="Search campaigns" x-model="search" :disabled="!loaded || loading">
                                        </div>
                                        <p class="mt-2 text-xs text-[var(--color-on-surface-muted)]" x-show="loading" x-cloak role="status">Loading campaigns from VICIdial…</p>
                                        <p class="mt-2 text-xs text-[var(--color-danger)]" x-show="error" x-text="error" x-cloak role="alert"></p>
                                        <div class="mt-3 grid max-h-64 grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3" role="group" aria-label="VICIdial campaign choices">
                                            <template x-for="campaign in filteredCampaigns" :key="campaign.code">
                                                <label class="flex min-h-[44px] cursor-pointer items-start gap-2 rounded-md border border-transparent px-2 py-2 text-sm hover:border-[var(--color-border)] hover:bg-[var(--color-surface-raised)] focus-within:ring-2 focus-within:ring-[var(--color-primary)]">
                                                    <input type="checkbox" name="vicidial_campaign_codes[]" class="mt-1 rounded border-[var(--color-border)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                                           :value="campaign.code" x-model="selected" :disabled="campaign.unavailable">
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-medium text-[var(--color-on-surface)]" x-text="campaign.code"></span>
                                                        <span class="block truncate text-xs text-[var(--color-on-surface-muted)]" x-text="campaign.name"></span>
                                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-[var(--color-warning)]" x-show="campaign.unavailable" x-cloak>Unavailable on VICIdial</span>
                                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-muted)]" x-show="!campaign.unavailable && campaign.is_active === false" x-cloak>Inactive</span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                        <p class="mt-3 text-xs text-[var(--color-on-surface-muted)]" x-show="loaded && filteredCampaigns.length === 0" x-cloak>No campaigns match the search.</p>
                                        <p class="mt-3 text-xs text-[var(--color-on-surface-dim)]" x-show="!loaded && !loading" x-cloak>Load the selected server's campaign catalog to choose mappings.</p>
                                    </div>
                                </fieldset>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button type="submit" class="btn-primary text-sm" :disabled="submitting || !selectedServerId || selected.length === 0">
                                    <x-icon name="check" class="w-4 h-4" />
                                    <span x-text="submitting ? 'Saving...' : 'Save VICIdial mapping'">Save VICIdial mapping</span>
                                </button>
                                <span class="text-xs text-[var(--color-on-surface-muted)]">At least one campaign is required. Changes apply to Supervisor and Reports.</span>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
        </tbody>
    @empty
        <x-table.empty :colspan="4" message="No campaigns yet." />
    @endforelse
</x-table.index>
@endsection

@push('scripts')
<script>
    window.campaignMappingEditor = function (config) {
        const savedMappings = Array.isArray(config.mappings) ? config.mappings : [];

        return {
            endpoint: config.endpoint,
            selectedServerId: config.selectedServerId ? String(config.selectedServerId) : '',
            mappings: savedMappings,
            campaigns: savedMappings.map((mapping) => ({
                code: String(mapping.campaign_code || ''),
                name: String(mapping.campaign_code || ''),
                is_active: mapping.status === 'active',
                unavailable: mapping.status === 'stale' || mapping.status === 'unavailable',
            })).filter((campaign) => campaign.code),
            selected: savedMappings.map((mapping) => String(mapping.campaign_code || '')).filter(Boolean),
            search: '',
            loading: false,
            loaded: false,
            submitting: false,
            error: '',

            get filteredCampaigns() {
                const term = this.search.trim().toLowerCase();

                return this.campaigns.filter((campaign) => !term
                    || campaign.code.toLowerCase().includes(term)
                    || campaign.name.toLowerCase().includes(term));
            },

            get selectionLabel() {
                return `${this.selected.length} campaign${this.selected.length === 1 ? '' : 's'} selected`;
            },

            async loadCampaigns(forceRefresh = false) {
                if (!this.selectedServerId) {
                    this.campaigns = [];
                    this.loaded = false;

                    return;
                }
                this.loading = true;
                this.error = '';
                try {
                    const url = new URL(this.endpoint, window.location.origin);
                    url.searchParams.set('server_id', this.selectedServerId);
                    if (forceRefresh) {
                        url.searchParams.set('refresh', '1');
                    }
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Unable to load VICIdial campaigns.');
                    }
                    const remote = Array.isArray(payload.campaigns) ? payload.campaigns : [];
                    const remoteCodes = new Set(remote.map((campaign) => String(campaign.code).toLowerCase()));
                    const unavailable = this.campaigns
                        .filter((campaign) => !remoteCodes.has(campaign.code.toLowerCase()))
                        .map((campaign) => ({ ...campaign, unavailable: true }));
                    this.campaigns = [...remote, ...unavailable]
                        .filter((campaign, index, all) => all.findIndex((item) => item.code.toLowerCase() === campaign.code.toLowerCase()) === index)
                        .map((campaign) => ({ ...campaign, code: String(campaign.code), name: String(campaign.name || campaign.code) }));
                    this.loaded = true;
                } catch (exception) {
                    this.error = exception.message || 'Unable to load VICIdial campaigns.';
                } finally {
                    this.loading = false;
                }
            },

            selectAll() {
                this.selected = [...new Set(this.campaigns.filter((campaign) => !campaign.unavailable).map((campaign) => campaign.code))];
            },

            clearAll() {
                this.selected = [];
            },
        };
    };
</script>
@endpush
