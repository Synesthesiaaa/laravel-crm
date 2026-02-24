<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallOriginated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $campaignCode,
        public readonly string $agent,
        public readonly string $phoneNumber,
        public readonly ?int $leadId = null
    ) {}
}
