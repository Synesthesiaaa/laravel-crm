@php
    $campaignName = session('campaign_name', 'CRM');
    $campaign     = session('campaign', '');
    $forms        = $campaignConfig['forms'] ?? [];
    /** Route `forms/{type}` — use route param for active state. */
    $formsRouteType = request()->route('type');

    /**
     * Active nav item: exact route name, or `admin.foo.*`-style group when the sidebar points at `admin.foo.index`.
     * Uses request()->routeIs() so matching stays aligned with Laravel's route naming.
     */
    $sidebarLinkActive = static function (string $itemRoute): bool {
        if ($itemRoute === '' || ! request()->route()) {
            return false;
        }
        if (request()->routeIs($itemRoute)) {
            return true;
        }
        if (str_ends_with($itemRoute, '.index')) {
            $base = substr($itemRoute, 0, -strlen('.index'));

            return request()->routeIs($base.'.*');
        }

        return false;
    };

    $navItems = [
        ['route' => 'dashboard',    'label' => 'Dashboard',    'icon' => 'chart-bar'],
        ['route' => 'records.index','label' => 'Call History', 'icon' => 'clipboard-document-list'],
    ];
    $telephonyItems = [
        ['route' => 'agent.index',    'label' => 'Agent Screen', 'icon' => 'speaker-wave'],
        ['route' => 'attendance.index','label' => 'Attendance',  'icon' => 'clock'],
    ];
    $adminItems = [
        ['route' => 'admin.dashboard',               'label' => 'Mgt Dashboard',       'icon' => 'shield-check'],
        ['route' => 'admin.supervisor',              'label' => 'Supervisor',          'icon' => 'signal'],
        ['route' => 'admin.attendance.index',         'label' => 'Staff Attendance',    'icon' => 'clock'],
        ['route' => 'admin.records.index',            'label' => 'Records List',        'icon' => 'table-cells'],
        ['route' => 'admin.data-master.index',        'label' => 'Data Master',         'icon' => 'list-bullet'],
        ['route' => 'admin.leads.lists.index',        'label' => 'Leads',               'icon' => 'queue-list'],
        ['route' => 'reports.index',                  'label' => 'Reports',             'icon' => 'chart-pie'],
        ['route' => 'admin.disposition-records.index','label' => 'Disposition Records', 'icon' => 'clipboard-document-list'],
        ['route' => 'admin.disposition-codes.index',  'label' => 'Disposition Codes',   'icon' => 'tag'],
        ['route' => 'admin.field-logic.index',        'label' => 'Field Logic',         'icon' => 'cog-6-tooth'],
        ['route' => 'admin.extraction.index',         'label' => 'Extraction',          'icon' => 'arrow-down-tray'],
    ];
    $superAdminItems = [
        ['route' => 'admin.users.index',           'label' => 'User Access',      'icon' => 'users'],
        ['route' => 'admin.vicidial-servers.index', 'label' => 'ViciDial Servers', 'icon' => 'server'],
        ['route' => 'admin.campaigns.index',        'label' => 'Campaigns',        'icon' => 'building-office'],
        ['route' => 'admin.forms.index',            'label' => 'Forms',            'icon' => 'document-text'],
        ['route' => 'admin.agent-screen.index',     'label' => 'Agent Screen Cfg', 'icon' => 'computer-desktop'],
        ['route' => 'admin.leads.fields.index',     'label' => 'Lead Fields Cfg',  'icon' => 'adjustments-horizontal'],
        ['route' => 'admin.attendance-statuses.index', 'label' => 'Attendance Statuses', 'icon' => 'clock'],
        ['route' => 'admin.configuration',          'label' => 'Configuration',    'icon' => 'cog-6-tooth'],
    ];
@endphp

<aside id="sidebar"
       class="md-sidebar"
       :class="{
           'sidebar-collapsed': $store.sidebar.collapsed,
           'sidebar-mobile-open': $store.sidebar.mobileOpen,
       }"
       x-data
       role="navigation"
       aria-label="Main navigation">

    <div class="sidebar-header">
        <div class="sidebar-logo shrink-0">
            <x-icon name="signal" class="w-5 h-5 text-[var(--color-primary)]" />
        </div>
        <span class="sidebar-brand-text font-bold uppercase tracking-wider text-[var(--color-on-surface)] truncate">
            {{ $campaignName }}
        </span>
        {{-- Mobile close button --}}
        <button type="button"
                class="lg:hidden ml-auto btn-icon shrink-0"
                @click="$store.sidebar.closeMobile()"
                aria-label="Close navigation">
            <x-icon name="x-mark" class="w-4 h-4" />
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
        {{-- Main --}}
        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
               title="{{ $item['label'] }}"
               @click="$store.sidebar.closeMobile()">
                <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                <span class="sidebar-item-label">{{ $item['label'] }}</span>
            </a>
        @endforeach

        {{-- Telephony section --}}
        <div class="sidebar-section-label">Telephony</div>
        @foreach($telephonyItems as $item)
            <a href="{{ route($item['route']) }}"
               class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
               title="{{ $item['label'] }}"
               @click="$store.sidebar.closeMobile()">
                <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                <span class="sidebar-item-label">{{ $item['label'] }}</span>
            </a>
        @endforeach

        {{-- Campaign Forms --}}
        @if(!empty($forms))
        <div class="sidebar-section-label">Campaign Forms</div>
        @foreach($forms as $formCode => $formConfig)
            <a href="{{ route('forms.show', ['type' => $formCode, 'campaign' => $campaign]) }}"
               class="sidebar-item {{ (request()->routeIs('forms.show') && (string) $formsRouteType === (string) $formCode) ? 'active' : '' }}"
               title="{{ $formConfig['name'] ?? $formCode }}"
               @click="$store.sidebar.closeMobile()">
                <x-icon name="document-text" class="sidebar-icon shrink-0" />
                <span class="sidebar-item-label truncate">{{ $formConfig['name'] ?? $formCode }}</span>
            </a>
        @endforeach
        @endif

        {{-- Admin section --}}
        @if($user && $user->isTeamLeader())
        <div class="sidebar-section-label">Admin</div>
        @foreach($adminItems as $item)
            <a href="{{ route($item['route']) }}"
               class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
               title="{{ $item['label'] }}"
               @click="$store.sidebar.closeMobile()">
                <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                <span class="sidebar-item-label">{{ $item['label'] }}</span>
            </a>
        @endforeach

            {{-- Super Admin section --}}
            @if($user->isSuperAdmin())
            <div class="sidebar-section-label">Super Admin</div>
            @foreach($superAdminItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
                   title="{{ $item['label'] }}"
                   @click="$store.sidebar.closeMobile()">
                    <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                    <span class="sidebar-item-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
            @endif
        @endif
    </nav>
</aside>
