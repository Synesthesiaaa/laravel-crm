<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class TelephonyFeatureService
{
    public const FEATURE_KEYS = [
        'session_controls',
        'ingroup_management',
        'transfer_controls',
        'recording_controls',
        'dtmf_controls',
        'callback_controls',
        'lead_tools',
        'predictive_dialing',
    ];

    private const CACHE_KEY = 'telephony_feature_flags_v1';

    public function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $rows = SystemSetting::query()
                ->whereIn('setting_key', $this->settingKeys())
                ->pluck('setting_value', 'setting_key')
                ->all();

            $result = [];
            foreach (self::FEATURE_KEYS as $key) {
                $settingKey = $this->toSettingKey($key);
                $result[$key] = $this->castBool($rows[$settingKey] ?? '1');
            }

            return $result;
        });
    }

    public function isEnabled(string $feature): bool
    {
        if (! in_array($feature, self::FEATURE_KEYS, true)) {
            return false;
        }

        $all = $this->getAll();

        return (bool) ($all[$feature] ?? false);
    }

    public function updateMany(array $values): void
    {
        foreach (self::FEATURE_KEYS as $feature) {
            $enabled = ! empty($values[$feature]);
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $this->toSettingKey($feature)],
                ['setting_value' => $enabled ? '1' : '0'],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    private function settingKeys(): array
    {
        return array_map(fn (string $f) => $this->toSettingKey($f), self::FEATURE_KEYS);
    }

    private function toSettingKey(string $feature): string
    {
        return 'telephony_feature_'.$feature;
    }

    private function castBool(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
