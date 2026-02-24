<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispositionSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $campaignCode,
        public readonly string $agent,
        public readonly string $dispositionCode,
        public readonly ?int $leadId = null
    ) {}
}
