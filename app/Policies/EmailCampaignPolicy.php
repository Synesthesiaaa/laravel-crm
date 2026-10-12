<?php

namespace App\Policies;

use App\Models\EmailCampaign;
use App\Models\User;

class EmailCampaignPolicy
{
    public function view(User $user, EmailCampaign $campaign): bool
    {
        return $user->role === User::ROLE_SUPER_ADMIN;
    }

    public function update(User $user, EmailCampaign $campaign): bool
    {
        return $this->view($user, $campaign) && $campaign->status === 'draft';
    }
}
