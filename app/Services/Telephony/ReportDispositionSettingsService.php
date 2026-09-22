<?php

namespace App\Services\Telephony;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportDispositionSettingsService
{
    public const HIDE_SYSTEM_DISPOSITIONS_KEY = 'reports_hide_system_dispositions';

    public const SYSTEM_DISPOSITION_CODES_KEY = 'reports_system_disposition_codes';

    private const CACHE_KEY = 'report_disposition_settings_v1';

    /**
     * @return array{hide_system_dispositions: bool, system_disposition_codes: list<string>, system_disposition_codes_text: string}
     */
    public function resolve(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $rows = SystemSetting::query()
                ->whereIn('setting_key', [self::HIDE_SYSTEM_DISPOSITIONS_KEY, self::SYSTEM_DISPOSITION_CODES_KEY])
                ->pluck('setting_value', 'setting_key')
                ->all();

            $hasConfiguredCodes = array_key_exists(self::SYSTEM_DISPOSITION_CODES_KEY, $rows);
            $codes = $hasConfiguredCodes
                ? $this->normalizeCodes((string) $rows[self::SYSTEM_DISPOSITION_CODES_KEY])
                : $this->normalizeCodes((array) config('vicidial.report_system_disposition_codes', []));

            return [
                'hide_system_dispositions' => $this->castBool($rows[self::HIDE_SYSTEM_DISPOSITIONS_KEY] ?? '0'),
                'system_disposition_codes' => $codes,
                'system_disposition_codes_text' => implode(', ', $codes),
            ];
        });
    }

    public function hideSystemDispositions(): bool
    {
        return $this->resolve()['hide_system_dispositions'];
    }

    /**
     * @return list<string>
     */
    public function systemDispositionCodes(): array
    {
        return $this->resolve()['system_disposition_codes'];
    }

    /**
     * @param  string|array<int, mixed>  $codes
     * @return list<string>
     */
    public function normalizeCodes(string|array $codes): array
    {
        $values = is_array($codes)
            ? $codes
            : (preg_split('/[\s,;]+/', $codes, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $normalized = array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            $values,
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $code): bool => $code !== '',
        )));
    }

    public function update(bool $hideSystemDispositions, string $codes): void
    {
        $before = $this->resolve();
        $normalizedCodes = $this->normalizeCodes($codes);

        DB::transaction(function () use ($hideSystemDispositions, $normalizedCodes): void {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => self::HIDE_SYSTEM_DISPOSITIONS_KEY],
                ['setting_value' => $hideSystemDispositions ? '1' : '0'],
            );
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => self::SYSTEM_DISPOSITION_CODES_KEY],
                ['setting_value' => implode(',', $normalizedCodes)],
            );
        });

        $this->flush();
        $after = $this->resolve();
        if ($before === $after) {
            return;
        }

        $logger = activity('configuration')
            ->event('updated')
            ->withProperties([
                'attributes' => ['report_dispositions' => $after],
                'old' => ['report_dispositions' => $before],
            ]);

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger->log('Report disposition settings updated');
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function castBool(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
