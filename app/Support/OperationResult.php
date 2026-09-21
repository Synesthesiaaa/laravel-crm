<?php

namespace App\Support;

class OperationResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public mixed $data = null,
        /**
         * Safe transport and parser metadata. This must never contain secrets
         * or unmasked customer data.
         *
         * @var array<string, mixed>
         */
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, ?string $message = null, array $meta = []): self
    {
        return new self(true, $message, $data, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failure(string $message, mixed $data = null, array $meta = []): self
    {
        return new self(false, $message, $data, $meta);
    }
}
