<?php

namespace App\Support;

use App\Models\User;

final class AdminNavigation
{
    /**
     * Task groups use the same destinations as the management directory.
     * Route middleware remains the authority for access.
     *
     * @return list<array{key: string, label: string, icon: string, items: array}>
     */
    public static function workspaceGroups(User $user, bool $agentScreenVisible): array
    {
        if (! $user->isTeamLeader()) {
            return [];
        }

        $items = self::adminItems();
        if ($user->isSuperAdmin()) {
            $items = array_merge($items, self::superAdminItems());
        }
        $groups = [
            'sms' => ['SMS Management', 'chat-bubble-left-right', ['admin.sms.index']],
            'overview' => ['Overview', 'chart-bar', ['admin.dashboard']],
            'operations' => ['Operations', 'signal', ['admin.supervisor', 'admin.attendance.index', 'admin.records.index', 'admin.data-master.index', 'admin.capture-records.index', 'admin.disposition-records.index', 'admin.lead-hopper.index']],
            'analytics' => ['Analytics', 'chart-pie', ['reports.index', 'admin.extraction.index']],
            'management' => ['Management', 'users', ['admin.campaigns.index', 'admin.forms.index', 'admin.users.index', 'admin.field-logic.index', 'admin.disposition-codes.index', 'admin.email-campaigns.index']],
            'administration' => ['Administration', 'cog-6-tooth', ['admin.vicidial-servers.index', 'admin.email-configuration.index', 'admin.agent-screen.index', 'admin.attendance-statuses.index', 'admin.configuration', 'admin.activity-log.index']],
        ];
        $result = [];
        foreach ($groups as $key => [$label, $icon, $routes]) {
            $destinations = array_values(array_filter($items, static fn (array $item): bool => in_array($item['route'], $routes, true)
                && ($agentScreenVisible || $item['route'] !== 'admin.agent-screen.index')));
            if ($destinations !== []) {
                $result[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'items' => $destinations];
            }
        }

        return $result;
    }

    /**
     * Administration destinations shared by the sidebar and management dashboard.
     *
     * @return list<array{route: string, label: string, icon: string, desc: string}>
     */
    public static function adminItems(): array
    {
        return [
            ['route' => 'admin.dashboard', 'label' => 'Management Dashboard', 'icon' => 'shield-check', 'desc' => 'Overview of campaign activity and administration tools'],
            ['route' => 'admin.supervisor', 'label' => 'Supervisor', 'icon' => 'signal', 'desc' => 'Monitor active agents and campaign activity in real time'],
            ['route' => 'admin.attendance.index', 'label' => 'Staff Attendance', 'icon' => 'clock', 'desc' => 'Review realtime attendance sessions and login history'],
            ['route' => 'admin.records.index', 'label' => 'Records List', 'icon' => 'table-cells', 'desc' => 'Browse call history and form submissions'],
            ['route' => 'admin.data-master.index', 'label' => 'Data Master', 'icon' => 'list-bullet', 'desc' => 'Review and maintain campaign form records'],
            ['route' => 'admin.capture-records.index', 'label' => 'Capture Records', 'icon' => 'clipboard-document-check', 'desc' => 'Review and maintain agent capture submissions'],
            ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'chart-pie', 'desc' => 'Analyze campaign performance and telephony outcomes'],
            ['route' => 'admin.disposition-records.index', 'label' => 'Disposition Records', 'icon' => 'clipboard-document-list', 'desc' => 'Review lead and disposition history'],
            ['route' => 'admin.disposition-codes.index', 'label' => 'Disposition Codes', 'icon' => 'tag', 'desc' => 'Manage campaign-specific outcome codes'],
            ['route' => 'admin.field-logic.index', 'label' => 'Field Logic', 'icon' => 'cog-6-tooth', 'desc' => 'Configure form fields and validation rules'],
            ['route' => 'admin.extraction.index', 'label' => 'Extraction', 'icon' => 'arrow-down-tray', 'desc' => 'Export campaign form data to CSV'],
        ];
    }

    /**
     * Super Admin destinations shared by the sidebar and management dashboard.
     *
     * @return list<array{route: string, label: string, icon: string, desc: string}>
     */
    public static function superAdminItems(): array
    {
        return [
            ['route' => 'admin.sms.index', 'label' => 'SMS Management', 'icon' => 'chat-bubble-left-right', 'desc' => 'Manage consent-based SMS campaigns, history and GoIP gateways'],
            ['route' => 'admin.users.index', 'label' => 'User Access', 'icon' => 'users', 'desc' => 'Manage staff accounts, roles, and telephony credentials'],
            ['route' => 'admin.vicidial-servers.index', 'label' => 'VICIdial Servers', 'icon' => 'server', 'desc' => 'Manage API and database connections'],
            ['route' => 'admin.campaigns.index', 'label' => 'Campaigns', 'icon' => 'building-office', 'desc' => 'Configure CRM campaigns and VICIdial mappings'],
            ['route' => 'admin.forms.index', 'label' => 'Forms', 'icon' => 'document-text', 'desc' => 'Manage campaign form definitions'],
            ['route' => 'admin.email-campaigns.index', 'label' => 'Email Campaigns', 'icon' => 'envelope', 'desc' => 'Create email templates and manage recipient campaigns'],
            ['route' => 'admin.email-configuration.index', 'label' => 'Email Configuration', 'icon' => 'cog-6-tooth', 'desc' => 'Configure SMTP delivery and sender identity'],
            ['route' => 'admin.lead-hopper.index', 'label' => 'Lead Hopper', 'icon' => 'list-bullet', 'desc' => 'Import and stage leads for dialing'],
            ['route' => 'admin.agent-screen.index', 'label' => 'Agent Screen Configuration', 'icon' => 'computer-desktop', 'desc' => 'Configure agent screen fields and webforms'],
            ['route' => 'admin.attendance-statuses.index', 'label' => 'Attendance Statuses', 'icon' => 'clock', 'desc' => 'Manage staff attendance status types'],
            ['route' => 'admin.configuration', 'label' => 'Configuration', 'icon' => 'cog-6-tooth', 'desc' => 'Branding, reporting, telephony, and retention settings'],
            ['route' => 'admin.activity-log.index', 'label' => 'Activity Log', 'icon' => 'document-text', 'desc' => 'Review system-wide administrative activity'],
        ];
    }
}
