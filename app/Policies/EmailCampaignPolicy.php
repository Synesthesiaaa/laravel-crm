<?php

namespace App\Policies;

use App\Models\EmailCampaign;
use App\Models\User;

class EmailCampaignPolicy
{
    public function view(User $user, EmailCampaign $campaign): bool
    {
        return $user->role === User::ROLE_SUPER_ADMIN
            || ($user->role === User::ROLE_ADMIN
                && (int) $campaign->created_by_user_id === (int) $user->id
                && $campaign->purpose === 'marketing'
                && $campaign->attachment_mode === 'none'
                && ! $campaign->template()->where('pdf_enabled', true)->exists());
    }

    public function update(User $user, EmailCampaign $campaign): bool
    {
        return $this->view($user, $campaign) && $campaign->status === 'draft' && ! in_array($campaign->approval_state, ['queued', 'processing'], true);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
