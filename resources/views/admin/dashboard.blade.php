@extends('layouts.app')

@section('title', 'Management Dashboard - Admin')

@section('header-icon')
    <span class="text-[var(--color-primary)]">🛡</span>
@endsection

@section('header-title')
    Management Dashboard
@endsection

@section('content')
    <div class="space-y-8">
        <div class="md-hero">
            <h2 class="text-2xl font-bold mb-2 text-[var(--color-on-surface)]">Admin Control Center</h2>
            <p class="text-[var(--color-on-surface-muted)]">Campaign: <strong class="text-[var(--color-primary)]">{{ $campaignName }}</strong></p>
        </div>

        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest">Form Data Overview</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 animate-stagger">
            @foreach($stats as $formCode => $stat)
                <div class="md-card p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-[var(--color-on-surface-muted)] uppercase">{{ $stat['name'] }}</span>
                    </div>
                    <div class="text-2xl font-bold text-[var(--color-on-surface)]">{{ number_format($stat['count']) }}</div>
                    @if($formCode)
                        <a href="{{ route('admin.data-master.index', ['type' => $formCode]) }}" class="link-primary text-xs mt-2 inline-block">View records →</a>
                    @endif
                </div>
            @endforeach
            <div class="md-card p-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-[var(--color-on-surface-muted)] uppercase">System Users</span>
                </div>
                <div class="text-2xl font-bold text-[var(--color-on-surface)]">{{ number_format($userCount) }}</div>
                @if($user->isSuperAdmin())
                    <a href="{{ route('admin.users.index') }}" class="link-primary text-xs mt-2 inline-block">Manage Access →</a>
                @endif
            </div>
        </div>

        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mt-8">Admin</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 animate-stagger">
            <a href="{{ route('admin.records.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>📜</span></div>
                <div>
                    <h4 class="font-bold text-[var(--color-on-surface)]">Records List</h4>
                    <p class="text-[var(--color-on-surface-muted)] text-xs">View call history & submissions</p>
                </div>
            </a>
            <a href="{{ route('admin.data-master.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>💾</span></div>
                <div>
                    <h4 class="font-bold text-[var(--color-on-surface)]">Data Master</h4>
                    <p class="text-[var(--color-on-surface-muted)] text-xs">CRUD form data records</p>
                </div>
            </a>
            <a href="{{ route('admin.disposition-records.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>📞</span></div>
                <div>
                    <h4 class="font-bold text-[var(--color-on-surface)]">Disposition Records</h4>
                    <p class="text-[var(--color-on-surface-muted)] text-xs">Leads & disposition log</p>
                </div>
            </a>
            <a href="{{ route('admin.disposition-codes.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>🏷</span></div>
                <div>
                    <h4 class="font-bold text-[var(--color-on-surface)]">Disposition Codes</h4>
                    <p class="text-[var(--color-on-surface-muted)] text-xs">Manage codes per campaign</p>
                </div>
            </a>
            <a href="{{ route('admin.field-logic.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>📋</span></div>
                <div>
                    <h4 class="font-bold text-[var(--color-on-surface)]">Field Logic</h4>
                    <p class="text-[var(--color-on-surface-muted)] text-xs">Form field schemas</p>
                </div>
            </a>
            <a href="{{ route('admin.extraction.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>📥</span></div>
                <div>
                    <h4 class="font-bold text-[var(--color-on-surface)]">Extraction</h4>
                    <p class="text-[var(--color-on-surface-muted)] text-xs">Export data to CSV</p>
                </div>
            </a>
        </div>

        @if($user->isSuperAdmin())
            <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mt-8">Super Admin</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 animate-stagger">
                <a href="{{ route('admin.users.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                    <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>👥</span></div>
                    <div>
                        <h4 class="font-bold text-[var(--color-on-surface)]">User Access</h4>
                        <p class="text-[var(--color-on-surface-muted)] text-xs">Manage users & roles</p>
                    </div>
                </a>
                <a href="{{ route('admin.vicidial-servers.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                    <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>🖥</span></div>
                    <div>
                        <h4 class="font-bold text-[var(--color-on-surface)]">ViciDial Servers</h4>
                        <p class="text-[var(--color-on-surface-muted)] text-xs">API & DB connections</p>
                    </div>
                </a>
                <a href="{{ route('admin.campaigns.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                    <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>📢</span></div>
                    <div>
                        <h4 class="font-bold text-[var(--color-on-surface)]">Campaigns</h4>
                        <p class="text-[var(--color-on-surface-muted)] text-xs">Manage campaigns</p>
                    </div>
                </a>
                <a href="{{ route('admin.forms.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                    <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>📄</span></div>
                    <div>
                        <h4 class="font-bold text-[var(--color-on-surface)]">Forms</h4>
                        <p class="text-[var(--color-on-surface-muted)] text-xs">Form definitions</p>
                    </div>
                </a>
                <a href="{{ route('admin.agent-screen.index') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                    <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>🎧</span></div>
                    <div>
                        <h4 class="font-bold text-[var(--color-on-surface)]">Agent Screen</h4>
                        <p class="text-[var(--color-on-surface-muted)] text-xs">Agent screen fields</p>
                    </div>
                </a>
                <a href="{{ route('admin.configuration') }}" class="md-card flex items-center gap-4 p-4 no-underline">
                    <div class="bg-[var(--color-surface-2)] p-3 rounded-lg border border-[var(--color-border)]"><span>⚙</span></div>
                    <div>
                        <h4 class="font-bold text-[var(--color-on-surface)]">Configuration</h4>
                        <p class="text-[var(--color-on-surface-muted)] text-xs">System settings</p>
                    </div>
                </a>
            </div>
        @endif
    </div>
@endsection
