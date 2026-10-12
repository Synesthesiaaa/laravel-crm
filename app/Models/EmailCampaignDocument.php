<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'purged_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}
