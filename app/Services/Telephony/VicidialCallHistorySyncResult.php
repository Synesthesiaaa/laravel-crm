<?php

namespace App\Services\Telephony;

use App\Models\VicidialCallHistorySyncState;

final readonly class VicidialCallHistorySyncResult
{
    public function __construct(
        public bool $success,
        public string $state,
        public int $rowsReceived = 0,
        public int $rowsInserted = 0,
        public int $rowsUpdated = 0,
        public ?string $message = null,
        public bool $retryable = false,
        public ?VicidialCallHistorySyncState $syncState = null,
        public array $meta = [],
    ) {}

    public static function success(
        VicidialCallHistorySyncState $syncState,
        int $rowsReceived,
        int $rowsInserted,
        int $rowsUpdated,
        array $meta = [],
    ): self {
        return new self(
            success: true,
            state: $syncState->status,
            rowsReceived: $rowsReceived,
            rowsInserted: $rowsInserted,
            rowsUpdated: $rowsUpdated,
            syncState: $syncState,
            meta: $meta,
        );
    }

    public static function failure(
        string $state,
        string $message,
        bool $retryable = false,
        ?VicidialCallHistorySyncState $syncState = null,
        array $meta = [],
    ): self {
        return new self(
            success: false,
            state: $state,
            message: $message,
            retryable: $retryable,
            syncState: $syncState,
            meta: $meta,
        );
    }
}
