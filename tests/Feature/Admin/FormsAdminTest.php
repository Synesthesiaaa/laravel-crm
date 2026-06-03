<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
    }

    public function test_forms_index_uses_shared_table_component_and_action_links(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);

        app(CampaignService::class)->clearCampaignsCache();

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.forms.index', ['campaign' => 'mbsales']));

        $response->assertOk();
        $response->assertSee('md-table-wrap', false);
        $response->assertSee('Campaign forms', false);
        $response->assertSee('ezycash', false);
        $response->assertSee(route('admin.field-logic.index', ['form' => 'ezycash']), false);
        $response->assertSee('Edit', false);
        $response->assertDontSee('<<<<<<<', false);
        $response->assertSee('<tbody x-data="{ editOpen:', false);
        $response->assertSee('x-show="editOpen"', false);
        $response->assertSee('inline-edit-row', false);
        $response->assertDontSee('<tr x-data="{ editOpen:', false);
    }

    public function test_forms_validation_errors_reopen_inline_edit_for_edited_form(): void
    {
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->put(route('admin.forms.update', $form), [
                '_editing' => $form->id,
                'campaign_code' => 'mbsales',
                'form_code' => 'ezycash',
                'name' => '',
                'table_name' => 'ezycash',
            ])
            ->assertSessionHasErrors('name');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.forms.index', ['campaign' => 'mbsales']));

        $response->assertOk();
        $response->assertSee('editOpen: true', false);
        $response->assertSee('name="_editing"', false);
        $response->assertSee('value="'.$form->id.'"', false);
    }
}
