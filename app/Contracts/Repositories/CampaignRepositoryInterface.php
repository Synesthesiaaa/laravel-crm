<?php

namespace App\Contracts\Repositories;

use App\Models\Campaign;
use Illuminate\Support\Collection;

interface CampaignRepositoryInterface
{
    public function allActive(): Collection;

    public function findByCode(string $code): ?Campaign;

    public function getCampaignsWithForms(): array;

    public function getAllFormTableNames(): array;

    public function getFormConfig(string $campaignCode, string $formCode): ?array;
}
