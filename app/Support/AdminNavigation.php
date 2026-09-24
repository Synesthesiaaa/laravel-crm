<?php

namespace App\Support;

final class AdminNavigation
{
    /**
     * Super Admin destinations shared by the sidebar and management dashboard.
     *
     * @return list<array{route: string, label: string, icon: string, desc: string}>
     */
    public static function superAdminItems(): array
    {
        return [
            ['route' => 'admin.users.index', 'label' => 'User Access', 'icon' => 'users', 'desc' => 'Manage staff accounts, roles, and telephony credentials'],
            ['route' => 'admin.vicidial-servers.index', 'label' => 'VICIdial Servers', 'icon' => 'server', 'desc' => 'Manage API and database connections'],
            ['route' => 'admin.campaigns.index', 'label' => 'Campaigns', 'icon' => 'building-office', 'desc' => 'Configure CRM campaigns and VICIdial mappings'],
            ['route' => 'admin.forms.index', 'label' => 'Forms', 'icon' => 'document-text', 'desc' => 'Manage campaign form definitions'],
            ['route' => 'admin.lead-hopper.index', 'label' => 'Lead Hopper', 'icon' => 'list-bullet', 'desc' => 'Import and stage leads for dialing'],
            ['route' => 'admin.agent-screen.index', 'label' => 'Agent Screen Configuration', 'icon' => 'computer-desktop', 'desc' => 'Configure agent screen fields and webforms'],
            ['route' => 'admin.attendance-statuses.index', 'label' => 'Attendance Statuses', 'icon' => 'clock', 'desc' => 'Manage staff attendance status types'],
            ['route' => 'admin.configuration', 'label' => 'Configuration', 'icon' => 'cog-6-tooth', 'desc' => 'Branding, reporting, telephony, and retention settings'],
            ['route' => 'admin.activity-log.index', 'label' => 'Activity Log', 'icon' => 'document-text', 'desc' => 'Review system-wide administrative activity'],
        ];
    }
}
