<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadImported
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $campaignCode,
        public readonly int $importedCount,
        public readonly int $uploadedByUserId
    ) {}
}
