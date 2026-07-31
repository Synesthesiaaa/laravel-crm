<?php

namespace App\Services;

use App\Models\DashboardLayout;

class DashboardLayoutService
{
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

    /** @return array{sections: array<string, array{visible: bool, order: int}>} */
    public function defaultLayout(): array
    {
        return $this->buildLayout(array_keys(self::SECTION_DEFINITIONS), array_keys(self::SECTION_DEFINITIONS));
    }

    /** @return array{sections: array<string, array{visible: bool, order: int}>} */
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

        return $this->buildLayout($sectionOrder, $visibleSections);
    }

    /**
     * @param  array<int, string>  $sectionOrder
     * @param  array<int, string>  $visibleSections
     * @return array{sections: array<string, array{visible: bool, order: int}>}
     */
    public function saveForCampaign(string $campaignCode, array $sectionOrder, array $visibleSections): array
    {
        $layout = $this->buildLayout($sectionOrder, $visibleSections);

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
