<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SafeOperationFailure
{
    public static function result(Throwable $exception, string $operation, string $message): OperationResult
    {
        $reference = (string) Str::uuid();
        Log::error('CRM operation failed.', [
            'operation' => $operation,
            'reference' => $reference,
            'exception_class' => $exception::class,
        ]);

        return OperationResult::failure($message.' Reference: '.$reference);
    }
}
