<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $campaignCode,
        public readonly string $formType,
        public readonly int $recordId,
        public readonly string $agent
    ) {}
}
