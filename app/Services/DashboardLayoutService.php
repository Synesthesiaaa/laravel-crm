<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\DashboardLayout;
use Closure;
use Illuminate\Support\Facades\DB;

class DashboardLayoutService
{
    public const ACTIVITY_ALL = 'all';

    public const ACTIVITY_SINGLE = 'single';

    /** @var array<string, int> */
    private static array $campaignRevisions = [];

    /** @var array<string, array{revision: int, layout: array<string, mixed>}> */
    private array $campaignLayouts = [];

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

    /** @return array<string, string> */
    public static function activityModes(): array
    {
        return [
            self::ACTIVITY_ALL => 'All forms and periods',
            self::ACTIVITY_SINGLE => 'One form at a time',
        ];
    }

    /** @return array{sections: array<string, array{visible: bool, order: int}>, activity_mode: string, sales?: array<string, mixed>, amounts: array<string, bool>} */
    public function defaultLayout(): array
    {
        return $this->buildLayout(array_keys(self::SECTION_DEFINITIONS), array_keys(self::SECTION_DEFINITIONS))
            + ['amounts' => $this->normalizeAmounts(null), 'activity_mode' => self::ACTIVITY_ALL];
    }

    /** @return array{sections: array<string, array{visible: bool, order: int}>, activity_mode: string, amounts: array<string, bool>, sales?: array<string, mixed>} */
    public function getForCampaign(string $campaignCode): array
    {
        $revision = self::$campaignRevisions[$campaignCode] ?? 0;
        if (($this->campaignLayouts[$campaignCode]['revision'] ?? null) === $revision) {
            return $this->campaignLayouts[$campaignCode]['layout'];
        }

        $record = DashboardLayout::query()
            ->where('campaign_code', $campaignCode)
            ->first();

        if (! $record || ! is_array($record->layout)) {
            return $this->rememberCampaignLayout($campaignCode, $this->defaultLayout(), $revision);
        }

        $savedSections = $record->layout['sections'] ?? [];
        if (! is_array($savedSections)) {
            return $this->rememberCampaignLayout($campaignCode, $this->defaultLayout(), $revision);
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
        $layout['activity_mode'] = $this->normalizeActivityMode($record->layout['activity_mode'] ?? null);
        if (is_array($record->layout['sales'] ?? null)) {
            $layout['sales'] = $record->layout['sales'];
        }

        return $this->rememberCampaignLayout($campaignCode, $layout, $revision);
    }

    /**
     * @param  array<int, string>  $sectionOrder
     * @param  array<int, string>  $visibleSections
     * @param  array<string, mixed>|null  $salesConfig
     * @param  array<string, bool|int|string>|null  $amountConfig
     * @return array{sections: array<string, array{visible: bool, order: int}>, activity_mode: string, sales?: array<string, mixed>, amounts: array<string, bool>}
     */
    public function saveForCampaign(
        string $campaignCode,
        array $sectionOrder,
        array $visibleSections,
        ?array $salesConfig = null,
        bool $replaceSalesConfig = false,
        ?array $amountConfig = null,
        ?string $activityMode = null,
    ): array {
        $layout = $this->buildLayout($sectionOrder, $visibleSections);
        $saved = $this->storeCampaignLayout($campaignCode, function (?array $previous) use ($layout, $amountConfig, $activityMode, $salesConfig, $replaceSalesConfig): array {
            $layout['amounts'] = $this->normalizeAmounts(array_replace(
                $this->normalizeAmounts($previous['amounts'] ?? null),
                $amountConfig ?? [],
            ));
            $layout['activity_mode'] = $this->normalizeActivityMode($activityMode ?? $previous['activity_mode'] ?? null);

            if ($salesConfig !== null) {
                $layout['sales'] = $salesConfig;
            } elseif (! $replaceSalesConfig && is_array($previous['sales'] ?? null)) {
                $layout['sales'] = $previous['sales'];
            }

            return $layout;
        });

        return $this->rememberCampaignLayout($campaignCode, $saved, self::$campaignRevisions[$campaignCode]);
    }

    /**
     * Replace only the sales attribution settings for a campaign.
     *
     * Keeping this separate from the dashboard layout editor prevents a form
     * attribution change from rebuilding section visibility, order, or amount
     * settings from stale browser state.
     *
     * @param  array<string, mixed>|null  $salesConfig
     * @return array{sections: array<string, array{visible: bool, order: int}>, activity_mode: string, amounts: array<string, bool>, sales?: array<string, mixed>}
     */
    public function saveSalesForCampaign(string $campaignCode, ?array $salesConfig): array
    {
        $this->storeCampaignLayout($campaignCode, function (?array $previous) use ($salesConfig): array {
            $layout = $previous ?? $this->defaultLayout();
            if ($salesConfig === null) {
                unset($layout['sales']);
            } else {
                $layout['sales'] = $salesConfig;
            }

            return $layout;
        });

        return $this->getForCampaign($campaignCode);
    }

    /**
     * @param  Closure(?array): array  $update
     */
    private function storeCampaignLayout(string $campaignCode, Closure $update): array
    {
        $layout = DB::transaction(function () use ($campaignCode, $update): array {
            // Serialize layout and sales edits, including the first save for a campaign.
            Campaign::query()->where('code', $campaignCode)->lockForUpdate()->value('id');
            $record = DashboardLayout::query()->firstOrNew(['campaign_code' => $campaignCode]);
            $record->layout = $update(is_array($record->layout) ? $record->layout : null);
            $record->save();

            return $record->layout;
        }, 3);

        self::$campaignRevisions[$campaignCode] = (self::$campaignRevisions[$campaignCode] ?? 0) + 1;

        return $layout;
    }

    private function normalizeActivityMode(mixed $mode): string
    {
        return $mode === self::ACTIVITY_SINGLE ? self::ACTIVITY_SINGLE : self::ACTIVITY_ALL;
    }

    /** @param array<string, mixed> $layout */
    private function rememberCampaignLayout(string $campaignCode, array $layout, int $revision): array
    {
        $this->campaignLayouts[$campaignCode] = [
            'revision' => $revision,
            'layout' => $layout,
        ];

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
