<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadImportRow extends Model
{
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'lead_import_id',
        'row_number',
        'request_id',
        'status',
        'record_id',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'lead_import_id' => 'integer',
            'row_number' => 'integer',
            'record_id' => 'integer',
        ];
    }

    public function leadImport(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class);
    }
}
