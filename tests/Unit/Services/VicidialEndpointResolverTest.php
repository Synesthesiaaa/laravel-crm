<?php

namespace Tests\Unit\Services;

use App\Models\VicidialServer;
use App\Services\Telephony\VicidialEndpointResolver;
use PHPUnit\Framework\TestCase;

class VicidialEndpointResolverTest extends TestCase
{
    public function test_it_preserves_the_application_path_when_deriving_sibling_endpoints(): void
    {
        $server = new VicidialServer([
            'api_url' => 'https://dial.example/vicidial/agc/api.php',
        ]);
        $resolver = new VicidialEndpointResolver;

        $this->assertSame(
            'https://dial.example/vicidial/non_agent_api.php',
            $resolver->nonAgentApi($server),
        );
        $this->assertSame(
            'https://dial.example/vicidial/AST_timeonVDADall.php',
            $resolver->realtimeReport($server),
        );
    }

    public function test_it_prefers_an_explicit_server_non_agent_endpoint_over_a_derived_endpoint(): void
    {
        $server = new VicidialServer([
            'api_url' => 'https://agent.example/agc/api.php',
            'non_agent_api_url' => 'https://reports.example/vicidial/non_agent_api.php',
        ]);

        $this->assertSame(
            'https://reports.example/vicidial/non_agent_api.php',
            (new VicidialEndpointResolver)->nonAgentApi($server),
        );
    }
}
