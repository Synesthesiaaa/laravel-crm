<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $fillable = ['created_by_user_id', 'name', 'subject', 'html_body', 'pdf_body', 'pdf_password', 'pdf_enabled', 'body_format', 'design_json'];

    protected $hidden = ['pdf_password'];

    protected function casts(): array
    {
        return ['pdf_password' => 'encrypted', 'pdf_enabled' => 'boolean', 'design_json' => 'array'];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class);
    }
}
