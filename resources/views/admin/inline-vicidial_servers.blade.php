<x-page-header title="ViciDial Servers"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'ViciDial Servers' => null]" />

<x-validation-errors />

<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)] flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Server</h3>
        <x-alert type="info" class="text-xs py-1 px-2 m-0">
            <code class="text-xs">api_user</code> / <code class="text-xs">api_pass</code> are used by the Non-Agent API (reports, callbacks, lead ops).
        </x-alert>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.vicidial-servers.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider">Connection</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <x-form.select name="campaign_code" label="Campaign" required
                    :options="collect($campaigns)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->all()"
                    :empty="false" />
                <x-form.input name="server_name" label="Server Name" required placeholder="e.g. Main ViciDial" />
                <x-form.input name="api_url" type="url" label="Agent API URL" required placeholder="http://vici/agc/api.php" />
            </div>
            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider border-t border-[var(--color-border)] pt-4">Database</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <x-form.input name="db_host" label="DB Host" required placeholder="10.10.88.138" />
                <x-form.input name="db_username" label="DB Username" required placeholder="cron" />
                <x-form.input name="db_password" type="password" label="DB Password" />
                <x-form.input name="db_name" label="DB Name" placeholder="asterisk" />
                <x-form.input name="db_port" type="number" label="DB Port" placeholder="3306" />
            </div>
            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider border-t border-[var(--color-border)] pt-4">Non-Agent API Credentials</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <x-form.input name="api_user" label="API User" placeholder="Non-Agent API admin user" />
                <x-form.input name="api_pass" type="password" label="API Password" />
                <x-form.input name="source" label="Source Tag" placeholder="crm_tracker" />
            </div>
            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider border-t border-[var(--color-border)] pt-4">Status & Priority</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <x-form.input name="priority" type="number" label="Priority" placeholder="0 = highest" value="0" />
                <div class="form-field">
                    <label class="form-label">Flags</label>
                    <div class="flex items-center gap-4 mt-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" checked class="rounded">
                            Active
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" class="rounded">
                            Default
                        </label>
                    </div>
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span x-text="submitting ? 'Adding...' : 'Add Server'">Add Server</span>
                </button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="ViciDial servers">
    <x-table.head :columns="[
        ['label' => 'Campaign'],
        ['label' => 'Server Name'],
        ['label' => 'Agent API URL'],
        ['label' => 'Non-Agent API User'],
        ['label' => 'Status'],
        ['label' => 'Priority'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    @if(!isset($servers) || $servers->isEmpty())
        <x-table.empty :colspan="7" message="No servers configured." />
    @else
    @foreach($servers as $s)
    <tbody x-data="{ editOpen: false }">
        <tr>
            <td><x-badge type="info">{{ $s->campaign_code }}</x-badge></td>
            <td class="font-medium">{{ $s->server_name }}</td>
            <td class="font-mono text-xs text-[var(--color-on-surface-muted)] truncate max-w-xs">{{ $s->api_url }}</td>
            <td class="text-sm">
                @if($s->api_user)
                    <span class="font-mono text-[var(--color-on-surface-dim)]">{{ $s->api_user }}</span>
                @else
                    <span class="text-xs text-[var(--color-danger)]">Not set</span>
                @endif
            </td>
            <td>
                <div class="flex gap-1 flex-wrap">
                    <x-badge :type="$s->is_active ? 'success' : 'inactive'">{{ $s->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    @if($s->is_default)
                        <x-badge type="info">Default</x-badge>
                    @endif
                </div>
            </td>
            <td class="text-sm text-[var(--color-on-surface-muted)]">{{ $s->priority }}</td>
            <td>
                <div class="table-actions" x-data="{ async del(form) {
                    const ok = await Alpine.store('confirm').ask('Delete server?', '{{ addslashes($s->server_name) }} will be removed.');
                    if (ok) form.submit();
                }}">
                    <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                        <x-icon name="pencil" class="w-3.5 h-3.5" />
                        <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                    </button>
                    <form method="POST" action="{{ route('admin.vicidial-servers.destroy') }}" x-ref="delFormS{{ $s->id }}">
                        @csrf
                        <input type="hidden" name="id" value="{{ $s->id }}">
                        <button type="button" class="btn-danger text-xs px-2 py-1"
                                @click="del($refs['delFormS{{ $s->id }}'])">
                            <x-icon name="trash" class="w-3.5 h-3.5" />
                            Delete
                        </button>
                    </form>
                </div>
            </td>
        </tr>

        {{-- Inline edit row --}}
        <tr x-show="editOpen" class="inline-edit-row" x-collapse>
            <td colspan="7">
                <form method="POST" action="{{ route('admin.vicidial-servers.update', $s) }}"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider">Connection</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <x-form.select name="campaign_code" label="Campaign" required
                                    :options="collect($campaigns)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->all()"
                                    :selected="$s->campaign_code" :empty="false" />
                                <x-form.input name="server_name" label="Server Name" :value="old('server_name', $s->server_name)" required />
                                <x-form.input name="api_url" type="url" label="Agent API URL" :value="old('api_url', $s->api_url)" required />
                            </div>
                        </div>
                        <div class="border-t border-[var(--color-border)] pt-4">
                            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider">Database</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <x-form.input name="db_host" label="DB Host" :value="old('db_host', $s->db_host)" required />
                                <x-form.input name="db_username" label="DB Username" :value="old('db_username', $s->db_username)" required />
                                <x-form.input name="db_password" type="password" label="DB Password" help="Leave blank to keep current" />
                                <x-form.input name="db_name" label="DB Name" :value="old('db_name', $s->db_name)" />
                                <x-form.input name="db_port" type="number" label="DB Port" :value="old('db_port', $s->db_port)" />
                            </div>
                        </div>
                        <div class="border-t border-[var(--color-border)] pt-4">
                            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider">Non-Agent API Credentials</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <x-form.input name="api_user" label="API User" :value="old('api_user', $s->api_user)" />
                                <x-form.input name="api_pass" type="password" label="API Password" help="Leave blank to keep current" />
                                <x-form.input name="source" label="Source Tag" :value="old('source', $s->source)" />
                            </div>
                        </div>
                        <div class="border-t border-[var(--color-border)] pt-4">
                            <p class="text-xs font-semibold text-[var(--color-on-surface-muted)] mb-3 uppercase tracking-wider">Status & Priority</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <x-form.input name="priority" type="number" label="Priority" :value="old('priority', $s->priority)" />
                                <div class="form-field">
                                    <label class="form-label">Flags</label>
                                    <div class="flex items-center gap-4 mt-2">
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="checkbox" name="is_active" value="1" @checked($s->is_active) class="rounded">
                                            Active
                                        </label>
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="checkbox" name="is_default" value="1" @checked($s->is_default) class="rounded">
                                            Default
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                            <x-icon name="check" class="w-4 h-4" />
                            <span x-text="submitting ? 'Saving...' : 'Update Server'">Update Server</span>
                        </button>
                        <button type="button" class="btn-ghost text-sm" @click="editOpen = false">Cancel</button>
                    </div>
                </form>
            </td>
        </tr>
    </tbody>
    @endforeach
    @endif
</x-table.index>
