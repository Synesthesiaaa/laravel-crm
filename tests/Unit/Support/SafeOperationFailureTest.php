<?php

namespace Tests\Unit\Support;

use App\Support\SafeOperationFailure;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SafeOperationFailureTest extends TestCase
{
    public function test_failure_returns_safe_reference_and_logs_only_safe_context(): void
    {
        Log::spy();
        $secret = 'credential=super-secret; customer=private-value';

        $result = SafeOperationFailure::result(
            new RuntimeException($secret),
            'form_submission.save',
            'The record could not be saved.',
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('The record could not be saved. Reference: ', $result->message);
        $this->assertStringNotContainsString($secret, $result->message);
        Log::shouldHaveReceived('error')->once()->with('CRM operation failed.', Mockery::on(function (array $context): bool {
            return $context['operation'] === 'form_submission.save'
                && isset($context['reference'], $context['exception_class'])
                && ! isset($context['exception_message']);
        }));
    }
}
