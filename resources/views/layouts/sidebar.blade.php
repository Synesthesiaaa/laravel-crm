@php
    $campaign     = session('campaign', '');
    $forms        = $campaignConfig['forms'] ?? [];
    $agentScreenVisible = app(\App\Services\TelephonyFeatureService::class)->isEnabled('agent_screen_access');
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
        ['route' => 'admin.dashboard',               'label' => 'Management Dashboard', 'icon' => 'shield-check'],
        ['route' => 'admin.supervisor',              'label' => 'Supervisor',          'icon' => 'signal'],
        ['route' => 'admin.attendance.index',         'label' => 'Staff Attendance',    'icon' => 'clock'],
        ['route' => 'admin.records.index',            'label' => 'Records List',        'icon' => 'table-cells'],
        ['route' => 'admin.data-master.index',        'label' => 'Data Master',         'icon' => 'list-bullet'],
        ['route' => 'admin.capture-records.index',    'label' => 'Capture Records',     'icon' => 'clipboard-document-check'],
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
        ['route' => 'admin.lead-hopper.index',      'label' => 'Lead Hopper',      'icon' => 'list-bullet'],
        ['route' => 'admin.agent-screen.index',     'label' => 'Agent Screen Configuration', 'icon' => 'computer-desktop'],
        ['route' => 'admin.attendance-statuses.index', 'label' => 'Attendance Statuses', 'icon' => 'clock'],
        ['route' => 'admin.configuration',          'label' => 'Configuration',    'icon' => 'cog-6-tooth'],
        ['route' => 'admin.activity-log.index',     'label' => 'Activity Log',      'icon' => 'document-text'],
    ];
    $sidebarSectionActive = static function (array $items) use ($sidebarLinkActive): bool {
        foreach ($items as $item) {
            if ($sidebarLinkActive($item['route'])) {
                return true;
            }
        }

        return false;
    };
    $telephonySectionActive = $sidebarSectionActive($telephonyItems);
    $formsSectionActive = request()->routeIs('forms.show');
    $adminSectionActive = $sidebarSectionActive($adminItems);
    $superAdminSectionActive = $sidebarSectionActive($superAdminItems);
@endphp

<aside id="sidebar"
       class="md-sidebar"
       :class="{
           'sidebar-collapsed': $store.sidebar.collapsed,
           'sidebar-mobile-open': $store.sidebar.mobileOpen,
       }"
       x-data
       @keydown.escape="$store.sidebar.closeMobile()"
       role="navigation"
       aria-label="Main navigation">

    <div class="sidebar-header">
        <x-brand :branding="$branding" variant="sidebar" />
        {{-- Mobile close button --}}
        <button type="button"
                class="lg:hidden ml-auto btn-icon shrink-0"
                @click="$store.sidebar.closeMobile()"
                aria-label="Close navigation"
                aria-controls="sidebar">
            <x-icon name="x-mark" class="w-4 h-4" />
        </button>
    </div>

    <nav class="sidebar-nav"
         aria-label="Primary destinations"
         x-data="{
             expandedSections: @js([
                 'telephony' => $telephonySectionActive,
                 'forms' => $formsSectionActive,
                 'admin' => $adminSectionActive,
                 'super-admin' => $superAdminSectionActive,
             ]),
             toggleSection(section) {
                 this.expandedSections[section] = ! this.expandedSections[section];
             },
         }">
        {{-- Main --}}
        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
               title="{{ $item['label'] }}"
               @if($sidebarLinkActive($item['route'])) aria-current="page" @endif
               @click="$store.sidebar.closeMobile()">
                <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                <span class="sidebar-item-label">{{ $item['label'] }}</span>
            </a>
        @endforeach

        {{-- Telephony section --}}
        <button type="button"
                class="sidebar-section-toggle"
                @click="toggleSection('telephony')"
                :aria-expanded="expandedSections.telephony"
                aria-controls="sidebar-section-telephony"
                title="Toggle Telephony navigation">
            <x-icon name="phone" class="sidebar-section-toggle-icon" />
            <span class="sidebar-section-toggle-label">Telephony</span>
            <x-icon name="chevron-down" class="sidebar-section-chevron" x-bind:class="expandedSections.telephony ? 'is-open' : ''" />
        </button>
        <div id="sidebar-section-telephony" class="sidebar-section-items" x-show="expandedSections.telephony" :aria-hidden="! expandedSections.telephony">
            @foreach($telephonyItems as $item)
                @if($item['route'] !== 'agent.index' || $agentScreenVisible)
                <a href="{{ route($item['route']) }}"
                   class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
                   title="{{ $item['label'] }}"
                   @if($sidebarLinkActive($item['route'])) aria-current="page" @endif
                   @click="$store.sidebar.closeMobile()">
                    <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                    <span class="sidebar-item-label">{{ $item['label'] }}</span>
                </a>
                @endif
            @endforeach
        </div>

        {{-- Campaign Forms --}}
        @if(!empty($forms))
        <button type="button"
                class="sidebar-section-toggle"
                @click="toggleSection('forms')"
                :aria-expanded="expandedSections.forms"
                aria-controls="sidebar-section-forms"
                title="Toggle Campaign Forms navigation">
            <x-icon name="document-text" class="sidebar-section-toggle-icon" />
            <span class="sidebar-section-toggle-label">Campaign Forms</span>
            <x-icon name="chevron-down" class="sidebar-section-chevron" x-bind:class="expandedSections.forms ? 'is-open' : ''" />
        </button>
        <div id="sidebar-section-forms" class="sidebar-section-items" x-show="expandedSections.forms" :aria-hidden="! expandedSections.forms">
            @foreach($forms as $formCode => $formConfig)
                <a href="{{ route('forms.show', ['type' => $formCode, 'campaign' => $campaign]) }}"
                   class="sidebar-item {{ (request()->routeIs('forms.show') && (string) $formsRouteType === (string) $formCode) ? 'active' : '' }}"
                   title="{{ $formConfig['name'] ?? $formCode }}"
                   @if(request()->routeIs('forms.show') && (string) $formsRouteType === (string) $formCode) aria-current="page" @endif
                   @click="$store.sidebar.closeMobile()">
                    <x-icon name="document-text" class="sidebar-icon shrink-0" />
                    <span class="sidebar-item-label truncate">{{ $formConfig['name'] ?? $formCode }}</span>
                </a>
            @endforeach
        </div>
        @endif

        {{-- Admin section --}}
        @if($user && $user->isTeamLeader())
        <button type="button"
                class="sidebar-section-toggle"
                @click="toggleSection('admin')"
                :aria-expanded="expandedSections.admin"
                aria-controls="sidebar-section-admin"
                title="Toggle Administration navigation">
            <x-icon name="shield-check" class="sidebar-section-toggle-icon" />
            <span class="sidebar-section-toggle-label">Administration</span>
            <x-icon name="chevron-down" class="sidebar-section-chevron" x-bind:class="expandedSections.admin ? 'is-open' : ''" />
        </button>
        <div id="sidebar-section-admin" class="sidebar-section-items" x-show="expandedSections.admin" :aria-hidden="! expandedSections.admin">
            @foreach($adminItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
                   title="{{ $item['label'] }}"
                   @if($sidebarLinkActive($item['route'])) aria-current="page" @endif
                   @click="$store.sidebar.closeMobile()">
                    <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                    <span class="sidebar-item-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

            {{-- Super Admin section --}}
            @if($user->isSuperAdmin())
            <button type="button"
                    class="sidebar-section-toggle"
                    @click="toggleSection('super-admin')"
                    :aria-expanded="expandedSections['super-admin']"
                    aria-controls="sidebar-section-super-admin"
                    title="Toggle Super Admin navigation">
                <x-icon name="cog-6-tooth" class="sidebar-section-toggle-icon" />
                <span class="sidebar-section-toggle-label">Super Admin</span>
                <x-icon name="chevron-down" class="sidebar-section-chevron" x-bind:class="expandedSections['super-admin'] ? 'is-open' : ''" />
            </button>
            <div id="sidebar-section-super-admin" class="sidebar-section-items" x-show="expandedSections['super-admin']" :aria-hidden="! expandedSections['super-admin']">
                @foreach($superAdminItems as $item)
                    @if($item['route'] !== 'admin.agent-screen.index' || $agentScreenVisible)
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-item {{ $sidebarLinkActive($item['route']) ? 'active' : '' }}"
                       title="{{ $item['label'] }}"
                       @if($sidebarLinkActive($item['route'])) aria-current="page" @endif
                       @click="$store.sidebar.closeMobile()">
                        <x-icon :name="$item['icon']" class="sidebar-icon shrink-0" />
                        <span class="sidebar-item-label">{{ $item['label'] }}</span>
                    </a>
                    @endif
                @endforeach
            </div>
            @endif
        @endif
    </nav>
</aside>
