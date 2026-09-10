<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class LoggingConfigurationTest extends TestCase
{
    public function test_telephony_daily_channels_use_group_writable_file_permission(): void
    {
        foreach (['telephony', 'telephony-events', 'telephony-errors'] as $channelName) {
            $channel = config("logging.channels.{$channelName}");

            $this->assertSame('daily', $channel['driver']);
            $this->assertSame(0664, $channel['permission']);
        }
    }

    public function test_ami_listener_uses_the_shared_laravel_runtime_account(): void
    {
        $supervisorConfig = file_get_contents(base_path('deploy/supervisor/laravel-ami-listener.conf.example'));

        $this->assertIsString($supervisorConfig);
        $this->assertStringContainsString("\nuser=crm\n", $supervisorConfig);
        $this->assertStringNotContainsString("\nuser=www-data\n", $supervisorConfig);
    }
}
