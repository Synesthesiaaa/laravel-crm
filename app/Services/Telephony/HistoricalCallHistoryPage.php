<?php

namespace App\Services\Telephony;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class HistoricalCallHistoryPage
{
    /**
     * @param  array<string, mixed>  $filterOptions
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $sourceHealth
     */
    public function __construct(
        public bool $available,
        public string $state,
        public LengthAwarePaginator $records,
        public array $filterOptions,
        public array $scope,
        public array $sourceHealth,
        public ?string $message = null,
    ) {}

    public function hasData(): bool
    {
        return $this->records->total() > 0;
    }
}
