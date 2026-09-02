<?php

namespace App\Services\Telephony;

final readonly class HistoricalCallProviderResult
{
    /**
     * @param  array<int, HistoricalCallRecord>  $records
     * @param  array<string, mixed>  $filterOptions
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $success,
        public array $records = [],
        public int $total = 0,
        public array $filterOptions = [],
        public ?string $message = null,
        public array $meta = [],
    ) {}

    /**
     * @param  array<int, HistoricalCallRecord>  $records
     * @param  array<string, mixed>  $filterOptions
     * @param  array<string, mixed>  $meta
     */
    public static function success(array $records, int $total, array $filterOptions = [], array $meta = []): self
    {
        return new self(true, $records, $total, $filterOptions, null, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failure(string $message, array $meta = []): self
    {
        return new self(false, [], 0, [], $message, $meta);
    }
}
