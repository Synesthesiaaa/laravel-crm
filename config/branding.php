<?php

return [
    'cache_key' => 'branding.settings.v1',
    'cache_ttl' => 300,
    'disk' => env('BRANDING_FILESYSTEM_DISK', 'public'),
    'path' => 'branding',
    'default_name' => env('APP_NAME') ?: 'CRM',
    'default_favicon' => 'favicon.ico',
    'company_name_key' => 'branding_company_name',
    'logo_path_key' => 'branding_logo_path',
    'favicon_path_key' => 'branding_favicon_path',
    'max_company_name_length' => 120,
    'max_logo_kilobytes' => 5120,
    'max_favicon_kilobytes' => 2048,
];
