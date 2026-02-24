@extends('layouts.app')

@section('title', 'ViciDial Servers - Admin')
@section('header-icon')<x-icon name="server" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'ViciDial Servers')

@section('content')
<x-page-header title="ViciDial Servers"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'ViciDial Servers' => null]" />

<x-validation-errors />

<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Server</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.vicidial-servers.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <x-form.select name="campaign_code" label="Campaign" required
                    :options="collect($campaigns)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->all()"
                    :empty="false" />
                <x-form.input name="server_name" label="Server Name" required />
                <x-form.input name="api_url" type="url" label="API URL" required />
                <x-form.input name="db_host" label="DB Host" required />
                <x-form.input name="db_username" label="DB Username" required />
                <x-form.input name="db_password" type="password" label="DB Password" />
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    Add Server
                </button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="ViciDial servers">
    <x-table.head :columns="[['label' => 'Campaign'], ['label' => 'Server Name'], ['label' => 'API URL'], ['label' => 'Actions', 'align' => 'right']]" />
    @if(!isset($servers) || $servers->isEmpty())
        <x-table.empty :colspan="4" message="No servers configured." />
    @else
    <tbody>
        @foreach($servers as $s)
            <tr>
                <td><x-badge type="info">{{ $s->campaign_code }}</x-badge></td>
                <td class="font-medium">{{ $s->server_name }}</td>
                <td class="font-mono text-sm text-[var(--color-on-surface-muted)] truncate max-w-xs">{{ $s->api_url }}</td>
                <td>
                    <div class="table-actions" x-data="{ async del(form) {
                        const ok = await Alpine.store('confirm').ask('Delete server?', '{{ $s->server_name }} will be removed.');
                        if (ok) form.submit();
                    }}">
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
        @endforeach
    </tbody>
    @endif
</x-table.index>
@endsection
