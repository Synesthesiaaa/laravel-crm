@php
    $currentRoute = request()->route()?->getName() ?? '';
    $campaignName = session('campaign_name', 'CRM');
    $campaign = session('campaign', '');
    $forms = $campaignConfig['forms'] ?? [];
@endphp
<div id="sidebar" class="md-sidebar">
    <div class="sidebar-header">
        <span class="text-2xl opacity-90">◆</span>
        <span class="font-bold uppercase tracking-wider text-[var(--color-on-surface)]">{{ $campaignName }}</span>
    </div>
    <nav class="p-3 flex-1 overflow-y-auto space-y-0.5">
        <a href="{{ route('dashboard') }}" class="sidebar-item {{ $currentRoute === 'dashboard' ? 'active' : '' }}">
            <span>📊</span>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('records.index') }}" class="sidebar-item {{ $currentRoute === 'records.index' ? 'active' : '' }}">
            <span>📜</span>
            <span>Call History</span>
        </a>
        <div class="sidebar-label">Telephony</div>
        <a href="{{ route('agent.index') }}" class="sidebar-item {{ $currentRoute === 'agent.index' ? 'active' : '' }}">
            <span>🎧</span>
            <span>Agent</span>
        </a>
        <a href="{{ route('leads.index') }}" class="sidebar-item {{ $currentRoute === 'leads.index' ? 'active' : '' }}">
            <span>📇</span>
            <span>Leads</span>
        </a>
        <a href="{{ route('attendance.index') }}" class="sidebar-item {{ $currentRoute === 'attendance.index' ? 'active' : '' }}">
            <span>⏱</span>
            <span>Attendance</span>
        </a>
        <div class="sidebar-label">Campaign Forms</div>
        @forelse($forms as $formCode => $formConfig)
            <a href="{{ route('forms.show', ['type' => $formCode, 'campaign' => $campaign]) }}" class="sidebar-item {{ ($currentRoute === 'forms.show' && request('type') === $formCode) ? 'active' : '' }}">
                <span>📄</span>
                <span>{{ $formConfig['name'] ?? $formCode }}</span>
            </a>
        @empty
            <div class="px-4 py-2 text-xs text-[var(--color-on-surface-dim)] italic">No forms available</div>
        @endforelse
        @if($user && $user->isTeamLeader())
            <div class="sidebar-label">Admin</div>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ $currentRoute === 'admin.dashboard' ? 'active' : '' }}">
                <span>🛡</span>
                <span>Mgt Dashboard</span>
            </a>
            <a href="{{ route('admin.attendance.index') }}" class="sidebar-item {{ $currentRoute === 'admin.attendance.index' ? 'active' : '' }}">
                <span>⏱</span>
                <span>Staff Attendance</span>
            </a>
            <a href="{{ route('admin.records.index') }}" class="sidebar-item {{ $currentRoute === 'admin.records.index' ? 'active' : '' }}">
                <span>📜</span>
                <span>Records List</span>
            </a>
            <a href="{{ route('admin.data-master.index') }}" class="sidebar-item {{ str_starts_with($currentRoute ?? '', 'admin.data-master') ? 'active' : '' }}">
                <span>💾</span>
                <span>Data Master</span>
            </a>
            <a href="{{ route('admin.disposition-records.index') }}" class="sidebar-item {{ $currentRoute === 'admin.disposition-records.index' ? 'active' : '' }}">
                <span>📞</span>
                <span>Disposition Records</span>
            </a>
            <a href="{{ route('admin.disposition-codes.index') }}" class="sidebar-item {{ $currentRoute === 'admin.disposition-codes.index' ? 'active' : '' }}">
                <span>🏷</span>
                <span>Disposition Codes</span>
            </a>
            <a href="{{ route('admin.field-logic.index') }}" class="sidebar-item {{ $currentRoute === 'admin.field-logic.index' ? 'active' : '' }}">
                <span>📋</span>
                <span>Field Logic</span>
            </a>
            <a href="{{ route('admin.extraction.index') }}" class="sidebar-item {{ $currentRoute === 'admin.extraction.index' ? 'active' : '' }}">
                <span>📥</span>
                <span>Extraction</span>
            </a>
            @if($user->isSuperAdmin())
                <div class="sidebar-label">Super Admin</div>
                <a href="{{ route('admin.users.index') }}" class="sidebar-item {{ str_starts_with($currentRoute ?? '', 'admin.users') ? 'active' : '' }}">
                    <span>👥</span>
                    <span>User Access</span>
                </a>
                <a href="{{ route('admin.vicidial-servers.index') }}" class="sidebar-item {{ str_starts_with($currentRoute ?? '', 'admin.vicidial') ? 'active' : '' }}">
                    <span>🖥</span>
                    <span>ViciDial Servers</span>
                </a>
                <a href="{{ route('admin.campaigns.index') }}" class="sidebar-item {{ str_starts_with($currentRoute ?? '', 'admin.campaigns') ? 'active' : '' }}">
                    <span>📢</span>
                    <span>Campaigns</span>
                </a>
                <a href="{{ route('admin.forms.index') }}" class="sidebar-item {{ str_starts_with($currentRoute ?? '', 'admin.forms') ? 'active' : '' }}">
                    <span>📄</span>
                    <span>Forms</span>
                </a>
                <a href="{{ route('admin.agent-screen.index') }}" class="sidebar-item {{ str_starts_with($currentRoute ?? '', 'admin.agent-screen') ? 'active' : '' }}">
                    <span>🎧</span>
                    <span>Agent Screen</span>
                </a>
                <a href="{{ route('admin.configuration') }}" class="sidebar-item {{ $currentRoute === 'admin.configuration' ? 'active' : '' }}">
                    <span>⚙</span>
                    <span>Configuration</span>
                </a>
            @endif
        @endif
    </nav>
</div>
