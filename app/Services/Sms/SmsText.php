<?php

namespace App\Services\Sms;

use InvalidArgumentException;

final class SmsText
{
    public const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    public const GSM_EXTENDED = "\f^{}\\[~]|€";

    public static function phone(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^09[0-9]{9}$/D', $value)) {
            return '+63'.substr($value, 1);
        }
        if (preg_match('/^\+?639[0-9]{9}$/D', $value)) {
            return '+'.ltrim($value, '+');
        }

        throw new InvalidArgumentException('Use a Philippine mobile number: 09…, 639… or +639….');
    }

    public static function estimate(string $body): array
    {
        $basic = self::GSM_BASIC;
        $extended = self::GSM_EXTENDED;
        $units = 0;
        foreach (mb_str_split($body) as $char) {
            if (str_contains($basic, $char)) {
                $units++;
            } elseif (str_contains($extended, $char)) {
                $units += 2;
            } else {
                $units = (int) (strlen(mb_convert_encoding($body, 'UTF-16BE', 'UTF-8')) / 2);

                return ['encoding' => 'Unicode', 'units' => $units, 'segments' => $units <= 70 ? 1 : (int) ceil($units / 67)];
            }
        }

        return ['encoding' => 'GSM-7', 'units' => $units, 'segments' => $units <= 160 ? 1 : (int) ceil($units / 153)];
    }

    public static function render(string $body, string $name, string $campaign): string
    {
        $body = preg_replace_callback('/{{\s*([^{}]+)\s*}}/', static function ($match) use ($name, $campaign) {
            return match (trim($match[1])) {
                'recipient_name' => $name,
                'campaign_name' => $campaign,
                default => throw new InvalidArgumentException('Supported variables: recipient_name and campaign_name.'),
            };
        }, $body);
        if (str_contains($body, '{{') || str_contains($body, '}}')) {
            throw new InvalidArgumentException('Malformed template variable.');
        }

        return $body;
    }

    public static function assertSupported(string $body): void
    {
        $estimate = self::estimate($body);
        if (trim($body) === '' || $estimate['encoding'] !== 'GSM-7' || $estimate['segments'] !== 1 || str_contains($body, "\0")) {
            throw new InvalidArgumentException('This transport profile permits one nonempty GSM-7 segment (160 units). Unicode and multipart require firmware verification.');
        }
    }
}
