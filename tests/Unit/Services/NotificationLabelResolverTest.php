<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\User;
use App\Services\Notifications\NotificationLabelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLabelResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_labels_and_aliases_are_resolved_without_internal_codes(): void
    {
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash Application',
            'table_name' => 'ezycash',
        ]);
        $user = User::factory()->create([
            'full_name' => 'Alex Agent',
            'name' => null,
            'username' => 'alex',
            'vici_user' => '1001',
        ]);
        $resolver = app(NotificationLabelResolver::class);

        $this->assertSame('MB Sales', $resolver->campaign('mbsales'));
        $this->assertSame('EzyCash Application', $resolver->form('ezycash', 'mbsales'));
        $this->assertSame('Alex Agent', $resolver->agent('1001'));
        $this->assertSame(['Alex Agent', 'alex', '1001'], $resolver->aliases($user));
        $this->assertSame('Status update', $resolver->status('internal_status'));
        $this->assertSame('Recorded', $resolver->status('RECORDED'));
    }
}
