<?php

return [
    // These executables must be available on the queue/web server for OCR.
    'tesseract' => env('OCR_TESSERACT_BINARY', 'tesseract'),
    'pdftotext' => env('OCR_PDFTOTEXT_BINARY', 'pdftotext'),
    'pdftoppm' => env('OCR_PDFTOPPM_BINARY', 'pdftoppm'),
    'queue' => env('EMAIL_CAMPAIGN_QUEUE', 'email-campaigns'),
    'connection' => env('EMAIL_CAMPAIGN_CONNECTION', 'email-database'),
    'pdfinfo' => env('EMAIL_PDFINFO_BINARY', 'pdfinfo'),
    'max_file_bytes' => (int) env('EMAIL_DOCUMENT_MAX_BYTES', 10485760),
    'max_total_bytes' => (int) env('EMAIL_DOCUMENT_TOTAL_BYTES', 104857600),
    'max_files' => (int) env('EMAIL_DOCUMENT_MAX_FILES', 500),
    'max_recipients' => (int) env('EMAIL_MAX_RECIPIENTS', 10000),
    'retention_days' => (int) env('EMAIL_DOCUMENT_RETENTION_DAYS', 7),
    'scanner' => env('EMAIL_DOCUMENT_SCANNER'),
    'scan_required' => (bool) env('EMAIL_DOCUMENT_SCAN_REQUIRED', false),
    'payroll_enabled' => (bool) env('EMAIL_PAYROLL_ENABLED', false),
    'payroll_password_required' => (bool) env('EMAIL_PAYROLL_PASSWORD_REQUIRED', true),
    'live_enabled' => (bool) env('EMAIL_CAMPAIGN_LIVE_ENABLED', false),
    'allow_local' => (bool) env('EMAIL_CAMPAIGN_ALLOW_LOCAL', false),
];
