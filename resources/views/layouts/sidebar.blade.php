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
        ['route' => 'attendance.index','label' => 'Attendance',  'icon' => 'clock'],
        ['route' => 'agent.index',    'label' => 'Agent Screen', 'icon' => 'speaker-wave'],
    ];
    $workspaceGroups = $user ? \App\Support\AdminNavigation::workspaceGroups($user, $agentScreenVisible) : [];
    $expandedGroups = ['forms' => request()->routeIs('forms.show')];
    foreach ($workspaceGroups as $group) {
        $expandedGroups[$group['key']] = collect($group['items'])->contains(fn ($item) => $sidebarLinkActive($item['route']));
    }
@endphp

<aside id="sidebar"
       class="md-sidebar"
       :class="{
           'sidebar-collapsed': $store.sidebar.collapsed,
           'sidebar-mobile-open': $store.sidebar.mobileOpen,
       }"
       x-data
       x-init="$watch('$store.sidebar.collapsed', value => window.syncSidebarCollapsedState(value))"
       x-trap.inert.noscroll="$store.sidebar.mobileOpen"
       @keydown.escape="$store.sidebar.closeMobile()"
       >

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
             expandedSections: @js($expandedGroups),
             toggleSection(section) {
                 this.expandedSections[section] = ! this.expandedSections[section];
             },
         }">
        {{-- Main --}}
        <p class="sidebar-section-label">Workspace</p>
        @foreach($navItems as $item)
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

        {{-- Campaign Forms --}}
        @if(!empty($forms))
        <button type="button"
                class="sidebar-section-toggle"
                @click="$store.sidebar.collapsed && ! $store.sidebar.mobileOpen ? ($store.sidebar.toggle(), expandedSections.forms = true) : toggleSection('forms')"
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

        @foreach($workspaceGroups as $group)
        <button type="button" class="sidebar-section-toggle"
                @click="$store.sidebar.collapsed && ! $store.sidebar.mobileOpen ? ($store.sidebar.toggle(), expandedSections['{{ $group['key'] }}'] = true) : toggleSection('{{ $group['key'] }}')"
                :aria-expanded="expandedSections['{{ $group['key'] }}']"
                aria-controls="sidebar-section-{{ $group['key'] }}"
                title="Toggle {{ $group['label'] }} navigation">
            <x-icon :name="$group['icon']" class="sidebar-section-toggle-icon" />
            <span class="sidebar-section-toggle-label">{{ $group['label'] }}</span>
            <x-icon name="chevron-down" class="sidebar-section-chevron" x-bind:class="expandedSections['{{ $group['key'] }}'] ? 'is-open' : ''" />
        </button>
        <div id="sidebar-section-{{ $group['key'] }}" class="sidebar-section-items"
             x-show="expandedSections['{{ $group['key'] }}']" :aria-hidden="! expandedSections['{{ $group['key'] }}']">
            @foreach($group['items'] as $item)
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
        @endforeach
    </nav>

    <div class="sidebar-workspace" title="{{ session('campaign_name', $campaign) }}">
        <span class="sidebar-workspace-mark" aria-hidden="true"><x-icon name="building-office" class="w-4 h-4" /></span>
        <div class="sidebar-workspace-copy">
            <span class="sidebar-workspace-label">Current campaign</span>
            <span class="sidebar-workspace-name">{{ session('campaign_name') ?: ($campaign ?: 'No campaign selected') }}</span>
        </div>
    </div>
</aside>
