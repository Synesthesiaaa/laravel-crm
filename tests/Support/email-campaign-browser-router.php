<?php

// Run only with: php -S 127.0.0.1:8879 -t public tests/Support/email-campaign-browser-router.php
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$workspace = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicFile = realpath($workspace.'/public'.$path);
if ($path !== '/' && $publicFile && str_starts_with($publicFile, realpath($workspace.'/public').DIRECTORY_SEPARATOR) && is_file($publicFile)) {
    return false;
}
require_once __DIR__.'/email-campaign-browser-fixture.php';
$app = emailCampaignBrowserApplication();
$app->handleRequest(Illuminate\Http\Request::capture());
