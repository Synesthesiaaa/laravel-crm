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
        $this->assertStringContainsString('x-data="formVisibility({ submitting: false, autosave: true })"', $html);
        $this->assertStringContainsString('@submit.prevent="openReview()"', $html);
        $this->assertStringContainsString('x-show="reviewOpen"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('Back to Form', $html);
        $this->assertStringContainsString('Confirm &amp; Save', $html);
        $this->assertStringContainsString('aria-label="Quick form selection"', $html);
        $this->assertStringContainsString('formOptions', $html);
        $this->assertStringContainsString('selectForm($event.target.value)', $html);
        $this->assertStringContainsString('data-user-id="'.$user->id.'"', $html);
        $this->assertStringContainsString('data-campaign="mbsales"', $html);
    }

    public function test_widget_form_show_renders_the_same_async_submission_hooks(): void
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
            ->get(route('forms.show', ['type' => 'ezycash', 'campaign' => 'mbsales', 'widget_embed' => 1]));

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('x-data="formVisibility({ submitting: false, autosave: true })"', $html);
        $this->assertStringContainsString('@submit.prevent="openReview()"', $html);
        $this->assertStringContainsString('x-show="reviewOpen"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('Back to Form', $html);
        $this->assertStringContainsString('Confirm &amp; Save', $html);
        $this->assertStringContainsString('data-user-id="'.$user->id.'"', $html);
    }

    public function test_agent_sees_a_read_only_date_on_regular_crm_forms(): void
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
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('forms.show', ['type' => 'ezycash', 'campaign' => 'mbsales']));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<input\s+type="date"\s+name="date"[^>]*\sreadonly(?:\s|>)/',
            (string) $response->getContent(),
        );
    }

    public function test_elevated_roles_can_edit_the_date_on_regular_crm_forms(): void
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

        foreach ([User::ROLE_TEAM_LEADER, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)
                ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
                ->get(route('forms.show', ['type' => 'ezycash', 'campaign' => 'mbsales']));

            $response->assertOk();
            $this->assertDoesNotMatchRegularExpression(
                '/<input\s+type="date"\s+name="date"[^>]*\sreadonly(?:\s|>)/',
                (string) $response->getContent(),
            );
        }
    }
}
