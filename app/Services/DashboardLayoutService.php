<?php

namespace App\Services;

use App\Models\DashboardLayout;

class DashboardLayoutService
{
    /** @return array<string, string> */
    public static function amountDefinitions(): array
    {
        return [
            'enabled' => 'Show amounts on this campaign dashboard',
            'total' => 'Total amount card',
            'change' => 'Amount change card',
            'charts' => 'Amount chart option',
            'tables' => 'Amount tables and columns',
        ];
    }

    /** @return array<string, bool> */
    private function normalizeAmounts(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];

        return array_map(
            static fn (string $key): bool => (bool) ($settings[$key] ?? true),
            array_combine(array_keys(self::amountDefinitions()), array_keys(self::amountDefinitions())),
        );
    }

    /** @var array<string, string> */
    private const SECTION_DEFINITIONS = [
        'welcome' => 'Welcome hero',
        'kpis' => 'KPI cards and sales details',
        'activity' => 'Activity charts',
        'leaderboard' => 'Agent leaderboard',
        'campaign_report' => 'Campaign report',
        'forms' => 'Campaign forms',
        'quick_links' => 'Quick links',
    ];

    /** @return array<string, string> */
    public static function sectionDefinitions(): array
    {
        return self::SECTION_DEFINITIONS;
    }

    /** @return array{sections: array<string, array{visible: bool, order: int}>, sales?: array<string, mixed>, amounts: array<string, bool>} */
    public function defaultLayout(): array
    {
        return $this->buildLayout(array_keys(self::SECTION_DEFINITIONS), array_keys(self::SECTION_DEFINITIONS))
            + ['amounts' => $this->normalizeAmounts(null)];
    }

    /** @return array{sections: array<string, array{visible: bool, order: int}>, amounts: array<string, bool>, sales?: array<string, mixed>} */
    public function getForCampaign(string $campaignCode): array
    {
        $record = DashboardLayout::query()
            ->where('campaign_code', $campaignCode)
            ->first();

        if (! $record || ! is_array($record->layout)) {
            return $this->defaultLayout();
        }

        $savedSections = $record->layout['sections'] ?? [];
        if (! is_array($savedSections)) {
            return $this->defaultLayout();
        }

        uasort($savedSections, static function (mixed $left, mixed $right): int {
            $leftOrder = is_array($left) ? (int) ($left['order'] ?? PHP_INT_MAX) : PHP_INT_MAX;
            $rightOrder = is_array($right) ? (int) ($right['order'] ?? PHP_INT_MAX) : PHP_INT_MAX;

            return $leftOrder <=> $rightOrder;
        });

        $sectionOrder = array_keys($savedSections);
        $visibleSections = array_keys(array_filter(
            $savedSections,
            static fn (mixed $section): bool => is_array($section) && ($section['visible'] ?? false) === true,
        ));

        $layout = $this->buildLayout($sectionOrder, $visibleSections);
        $layout['amounts'] = $this->normalizeAmounts($record->layout['amounts'] ?? null);
        if (is_array($record->layout['sales'] ?? null)) {
            $layout['sales'] = $record->layout['sales'];
        }

        return $layout;
    }

    /**
     * @param  array<int, string>  $sectionOrder
     * @param  array<int, string>  $visibleSections
     * @param  array<string, mixed>|null  $salesConfig
     * @param  array<string, bool|int|string>|null  $amountConfig
     * @return array{sections: array<string, array{visible: bool, order: int}>, sales?: array<string, mixed>, amounts: array<string, bool>}
     */
    public function saveForCampaign(
        string $campaignCode,
        array $sectionOrder,
        array $visibleSections,
        ?array $salesConfig = null,
        bool $replaceSalesConfig = false,
        ?array $amountConfig = null,
    ): array {
        $layout = $this->buildLayout($sectionOrder, $visibleSections);
        $record = DashboardLayout::query()
            ->where('campaign_code', $campaignCode)
            ->first();

        $layout['amounts'] = $this->normalizeAmounts(array_replace(
            $this->normalizeAmounts($record?->layout['amounts'] ?? null),
            $amountConfig ?? [],
        ));

        if ($replaceSalesConfig && $salesConfig === null) {
            unset($layout['sales']);
        } elseif ($salesConfig !== null) {
            $layout['sales'] = $salesConfig;
        } elseif (is_array($record?->layout['sales'] ?? null)) {
            $layout['sales'] = $record->layout['sales'];
        }

        DashboardLayout::query()->updateOrCreate(
            ['campaign_code' => $campaignCode],
            ['layout' => $layout],
        );

        return $layout;
    }

    /**
     * @param  array<int, mixed>  $sectionOrder
     * @param  array<int, mixed>  $visibleSections
     * @return array{sections: array<string, array{visible: bool, order: int}>}
     */
    private function buildLayout(array $sectionOrder, array $visibleSections): array
    {
        $known = array_fill_keys(array_keys(self::SECTION_DEFINITIONS), true);
        $ordered = [];

        foreach ($sectionOrder as $section) {
            if (is_string($section) && isset($known[$section]) && ! in_array($section, $ordered, true)) {
                $ordered[] = $section;
            }
        }

        foreach (array_keys(self::SECTION_DEFINITIONS) as $section) {
            if (! in_array($section, $ordered, true)) {
                $ordered[] = $section;
            }
        }

        $visibleSections = array_values(array_unique(array_filter(
            $visibleSections,
            static fn (mixed $section): bool => is_string($section) && isset($known[$section]),
        )));
        if ($visibleSections === []) {
            $visibleSections = [$ordered[0]];
        }
        $visible = array_fill_keys($visibleSections, true);

        $sections = [];
        foreach ($ordered as $order => $section) {
            $sections[$section] = [
                'visible' => isset($visible[$section]),
                'order' => $order,
            ];
        }

        return ['sections' => $sections];
    }
}
