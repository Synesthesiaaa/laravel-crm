<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserImport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['source_digest'];

    protected function casts(): array
    {
        return [
            'allow_privileged_roles' => 'boolean', 'expires_at' => 'datetime', 'preview_counts' => 'array',
            'source_bytes' => 'integer', 'cursor_offset' => 'integer', 'cursor_record' => 'integer',
            'processed' => 'integer', 'accepted' => 'integer', 'skipped' => 'integer', 'rejected' => 'integer',
        ];
    }

    public function sourcePath(): string
    {
        return $this->id.'/source.csv';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled', 'expired'], true);
    }
}
