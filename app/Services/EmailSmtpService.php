<?php

namespace App\Services;

use App\Models\EmailSmtpSetting;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class EmailSmtpService
{
    public function settings(): ?EmailSmtpSetting
    {
        // Deliberately read from the database at send time so queue workers
        // use edits without relying on cached .env configuration.
        return EmailSmtpSetting::query()->first();
    }

    public function activeSettings(): ?EmailSmtpSetting
    {
        $settings = $this->settings();

        return $settings?->enabled ? $settings : null;
    }

    public function source(): string
    {
        if ($this->activeSettings()) {
            return 'database';
        }

        return in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'sendmail'], true)
            ? 'environment'
            : 'unconfigured';
    }

    public function isReady(): bool
    {
        return $this->source() !== 'unconfigured';
    }

    public function mailer(): Mailer
    {
        $settings = $this->activeSettings();
        if ($settings) {
            return Mail::build($this->transportConfiguration($settings));
        }

        return Mail::mailer();
    }

    /** @return array{0: string, 1: string} */
    public function sender(): array
    {
        $settings = $this->activeSettings();
        if ($settings) {
            return [$settings->from_address, $settings->from_name];
        }

        return [(string) config('mail.from.address'), (string) config('mail.from.name')];
    }

    /** @return array<string, mixed> */
    public function transportConfiguration(EmailSmtpSetting $settings): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $settings->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'password' => $settings->password,
            'auto_tls' => $settings->encryption !== 'none',
            'require_tls' => $settings->encryption === 'tls',
            'timeout' => 15,
        ];
    }
}
