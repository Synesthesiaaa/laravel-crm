<?php

namespace Tests\Unit\Services;

use App\Services\ActivityLogSanitizer;
use PHPUnit\Framework\TestCase;

class ActivityLogSanitizerTest extends TestCase
{
    public function test_sensitive_values_are_redacted_recursively(): void
    {
        $sanitized = (new ActivityLogSanitizer)->sanitize([
            'attributes' => [
                'username' => 'operator',
                'password' => 'do-not-store',
                'profile' => [
                    'api_token' => 'secret-token',
                    'label' => 'Visible label',
                ],
            ],
            'old' => [
                'sip_password' => 'old-sip-secret',
                'sort_order' => 3,
            ],
        ]);

        $this->assertSame('operator', $sanitized['attributes']['username']);
        $this->assertSame(ActivityLogSanitizer::REDACTED, $sanitized['attributes']['password']);
        $this->assertSame(ActivityLogSanitizer::REDACTED, $sanitized['attributes']['profile']['api_token']);
        $this->assertSame('Visible label', $sanitized['attributes']['profile']['label']);
        $this->assertSame(ActivityLogSanitizer::REDACTED, $sanitized['old']['sip_password']);
        $this->assertSame(3, $sanitized['old']['sort_order']);
    }
}
