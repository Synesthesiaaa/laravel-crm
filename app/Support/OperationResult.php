<?php

namespace App\Support;

class OperationResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public mixed $data = null,
    ) {}

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(true, $message, $data);
    }

    public static function failure(string $message, mixed $data = null): self
    {
        return new self(false, $message, $data);
    }
}
