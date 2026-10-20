<?php

namespace App\Support;

final class AdminNavigation
{
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
