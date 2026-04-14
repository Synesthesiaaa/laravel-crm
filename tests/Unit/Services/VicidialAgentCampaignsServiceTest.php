<?php

namespace Tests\Unit\Services;

use App\Services\Telephony\VicidialAgentCampaignsService;
use Tests\TestCase;

class VicidialAgentCampaignsServiceTest extends TestCase
{
    public function test_parse_agent_campaigns_response_hyphen_separated(): void
    {
        $raw = <<<'TXT'
user|allowed_campaigns_list|allowed_ingroups_list
1000|TESTCAMP-TEST123-TEST987|ING1-ING2
TXT;
        $s = app(VicidialAgentCampaignsService::class);
        $ids = $s->parseAgentCampaignsResponse($raw);
        $this->assertSame(['TESTCAMP', 'TEST123', 'TEST987'], $ids);
    }

    public function test_parse_skips_header_and_error_lines(): void
    {
        $raw = "ERROR: something\nuser|allowed_campaigns_list|allowed_ingroups_list\nbob|A-B|X";
        $s = app(VicidialAgentCampaignsService::class);
        $ids = $s->parseAgentCampaignsResponse($raw);
        $this->assertSame(['A', 'B'], $ids);
    }
}
