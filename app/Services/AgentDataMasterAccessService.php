<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AgentDataMasterAccessService
{
    /** @return array{enabled: bool, own_submissions_only: bool, form_ids: list<int>} */
    public function settings(string $campaign): array
    {
        $defaults = ['enabled' => false, 'own_submissions_only' => true, 'form_ids' => []];
        $stored = json_decode((string) SystemSetting::query()->where('setting_key', $this->key($campaign))->value('setting_value'), true);
        if (! is_array($stored)
            || ! is_bool($stored['enabled'] ?? null)
            || ! is_bool($stored['own_submissions_only'] ?? null)
            || ! is_array($stored['form_ids'] ?? null)
            || ! array_is_list($stored['form_ids'])
            || array_filter($stored['form_ids'], fn ($id) => ! is_int($id) || $id < 1) !== []) {
            return $defaults;
        }

        return array_intersect_key($stored, $defaults);
    }

    /** @return Collection<int, Form> */
    public function allowedForms(string $campaign): Collection
    {
        return Form::query()->active()->where('campaign_code', $campaign)
            ->whereHas('campaign', fn ($query) => $query->active())
            ->whereIn('id', $this->settings($campaign)['form_ids'])
            ->orderBy('display_order')->orderBy('id')->get();
    }

    public function canView(User $user, string $campaign): bool
    {
        return in_array($user->role, User::ROLES, true)
            && $this->settings($campaign)['enabled']
            && $this->allowedForms($campaign)->isNotEmpty();
    }

    public function update(string $campaign, array $values): void
    {
        abort_unless(Campaign::query()->active()->where('code', $campaign)->exists(), 404);
        SystemSetting::query()->updateOrCreate(['setting_key' => $this->key($campaign)], ['setting_value' => json_encode([
            'enabled' => (bool) $values['enabled'],
            'own_submissions_only' => (bool) ($values['own_submissions_only'] ?? true),
            'form_ids' => array_values(array_unique(array_map('intval', $values['form_ids'] ?? []))),
        ], JSON_THROW_ON_ERROR)]);
    }

    private function key(string $campaign): string
    {
        return 'agent_data_master_'.$campaign;
    }
}
