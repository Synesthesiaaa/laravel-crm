<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserWidgetLayout;

class WidgetLayoutService
{
    /** @return array<string, array<string, mixed>> */
    public function getLayoutsForUser(User $user): array
    {
        return UserWidgetLayout::query()
            ->where('user_id', $user->id)
            ->get(['widget_key', 'layout'])
            ->pluck('layout', 'widget_key')
            ->all();
    }

    /** @param array<string, mixed> $layout */
    public function saveLayout(User $user, string $widgetKey, array $layout): array
    {
        $record = UserWidgetLayout::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'widget_key' => $widgetKey,
            ],
            [
                'layout' => $layout,
            ],
        );

        return $record->layout ?? [];
    }
}
