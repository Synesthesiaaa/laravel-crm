<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormShowViewRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_show_does_not_render_literal_blade_js_directive_in_alpine_bindings(): void
    {
        Campaign::create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'description' => 'Test campaign',
            'display_order' => 0,
        ]);
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
        ]);
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'cardholder_name',
            'field_label' => 'Cardholder Name',
            'field_type' => 'text',
            'is_required' => true,
            'field_order' => 1,
            'field_width' => 'full',
            'visibility' => [
                'field' => 'account_type',
                'operator' => 'equals',
                'values' => ['Savings'],
            ],
        ]);

        $user = User::factory()->create(['role' => User::ROLE_AGENT]);
        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('forms.show', ['type' => 'ezycash', 'campaign' => 'mbsales']));

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringNotContainsString('@js(', $html);
        $this->assertStringContainsString('x-bind:disabled="!isVisible(', $html);
    }
}
