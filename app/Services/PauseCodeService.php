<?php

namespace App\Services;

use App\Models\PauseCode;
use Illuminate\Support\Facades\Cache;

class PauseCodeService
{
    private const CACHE_KEY = 'pause_codes_agent_select_v1';

    /**
     * @return list<array{code: string, label: string}>
     */
    public function codesForAgentSelect(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $rows = PauseCode::query()->active()->orderBy('sort_order')->orderBy('code')->get(['code', 'label']);
            if ($rows->isEmpty()) {
                return $this->fallbackFromConfig();
            }

            return $rows->map(fn (PauseCode $r) => [
                'code' => $r->code,
                'label' => $r->label !== '' ? $r->label : $r->code,
            ])->values()->all();
        });
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function fallbackFromConfig(): array
    {
        $codes = config('vicidial.pause_codes', ['BREAK']);

        return collect($codes)->map(fn (string $c) => [
            'code' => $c,
            'label' => $c,
        ])->values()->all();
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function defaultPauseCode(): string
    {
        $list = $this->codesForAgentSelect();

        return $list[0]['code'] ?? 'BREAK';
    }
}
