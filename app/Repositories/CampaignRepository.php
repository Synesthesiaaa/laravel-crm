<?php

namespace App\Repositories;

use App\Contracts\Repositories\CampaignRepositoryInterface;
use App\Models\Campaign;
use App\Models\Form;
use Illuminate\Support\Collection;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function allActive(): Collection
    {
        return Campaign::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    public function findByCode(string $code): ?Campaign
    {
        return Campaign::where('code', $code)->where('is_active', true)->first();
    }

    public function getCampaignsWithForms(): array
    {
        $campaigns = $this->allActive();
        $forms = Form::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->groupBy('campaign_code');

        $result = [];
        foreach ($campaigns as $campaign) {
            $result[$campaign->code] = [
                'name' => $campaign->name,
                'description' => $campaign->description ?? '',
                'color' => $campaign->color ?? 'blue',
                'forms' => [],
            ];
            $campaignForms = $forms->get($campaign->code, collect());
            foreach ($campaignForms as $form) {
                $result[$campaign->code]['forms'][$form->form_code] = [
                    'name' => $form->name,
                    'table' => $form->table_name,
                    'table_name' => $form->table_name,
                    'color' => $form->color ?? 'blue',
                    'icon' => $form->icon ?? 'form',
                ];
            }
        }

        return $result;
    }

    public function getAllFormTableNames(): array
    {
        return Form::where('is_active', true)->pluck('table_name')->unique()->values()->all();
    }

    public function getFormConfig(string $campaignCode, string $formCode): ?array
    {
        $form = Form::where('campaign_code', $campaignCode)
            ->where('form_code', $formCode)
            ->where('is_active', true)
            ->first();
        if (! $form) {
            return null;
        }

        return [
            'name' => $form->name,
            'table_name' => $form->table_name,
            'color' => $form->color ?? 'blue',
            'icon' => $form->icon ?? 'form',
        ];
    }
}
