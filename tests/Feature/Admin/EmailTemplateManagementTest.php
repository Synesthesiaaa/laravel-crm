<?php

namespace Tests\Feature\Admin;

use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailSmtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_owned_sanitized_html_template(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($actor)->post('/admin/email-templates', $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $template = EmailTemplate::firstOrFail();
        $this->assertSame($actor->id, $template->created_by_user_id);
        $this->assertSame('html', $template->body_format);
        $this->assertStringNotContainsString('onclick', $template->html_body);
        $this->assertFalse($template->pdf_enabled);
    }

    public function test_other_owner_and_confidential_templates_are_inaccessible_to_admin(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $private = EmailTemplate::create([...$this->payload(), 'created_by_user_id' => $other->id]);
        $this->actingAs($actor)->get('/admin/email-templates/'.$private->id.'/edit')->assertNotFound();

        $private->update(['created_by_user_id' => $actor->id, 'pdf_enabled' => true]);
        $this->actingAs($actor)->get('/admin/email-templates/'.$private->id.'/edit')->assertNotFound();
    }

    public function test_agent_cannot_create_or_preview_templates(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $this->actingAs($actor)->post('/admin/email-templates', $this->payload())->assertForbidden();
        $this->actingAs($actor)->postJson('/admin/email-templates/preview', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_preview_uses_synthetic_values_and_returns_inert_sanitized_source(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($actor)->postJson('/admin/email-templates/preview', $this->payload())->assertOk();
        $this->assertStringContainsString('Sample Recipient', $response->json('html'));
        $this->assertStringContainsString('{{name}}', $response->json('source'));
        $this->assertStringNotContainsString('onclick', $response->json('html'));
        $this->assertStringNotContainsString($actor->email, $response->json('html'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_scheduled_template_is_immutable(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $template = EmailTemplate::create([...$this->payload(), 'created_by_user_id' => $actor->id]);
        EmailCampaign::create(['name' => 'Scheduled', 'email_template_id' => $template->id, 'created_by_user_id' => $actor->id, 'purpose' => 'marketing', 'attachment_mode' => 'none', 'status' => 'scheduled']);
        $this->actingAs($actor)->put('/admin/email-templates/'.$template->id, [...$this->payload(), 'name' => 'Changed'])
            ->assertSessionHasErrors('template');
        $this->assertSame('Reusable', $template->fresh()->name);
    }

    public function test_rendered_self_test_ignores_recipient_input_and_never_attaches_documents(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'operator@example.com']);
        $template = EmailTemplate::create([...$this->payload(), 'created_by_user_id' => $actor->id]);
        $smtp = $this->mock(EmailSmtpService::class);
        $smtp->shouldReceive('isReady')->once()->andReturnTrue();
        $smtp->shouldReceive('sender')->once()->andReturn(['sender@example.com', 'CRM']);
        $smtp->shouldReceive('mailer')->once()->andReturn(Mail::mailer('array'));
        $messages = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$messages): void {
            $messages[] = $event->message;
        });
        $this->actingAs($actor)->post('/admin/email-templates/'.$template->id.'/test', ['recipient' => 'other@example.com'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(1, $messages);
        $this->assertSame('operator@example.com', $messages[0]->getTo()[0]->getAddress());
        $this->assertCount(1, $messages[0]->getTo());
        $this->assertCount(0, $messages[0]->getAttachments());
        $this->assertStringContainsString('Sample Recipient', $messages[0]->getHtmlBody());
        $this->assertCount(0, $messages[0]->getCc());
        $this->assertCount(0, $messages[0]->getBcc());
    }

    public function test_editor_has_scriptless_preview_and_accessible_source_fallback(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($actor)->get('/admin/email-templates/create')->assertOk()
            ->assertSee('sandbox=""', false)->assertSee('HTML source')->assertSee('Move up')->assertSee('Move down');
    }

    private function payload(): array
    {
        return ['name' => 'Reusable', 'subject' => 'Hello {{name}}', 'body_format' => 'html', 'html_body' => '<p onclick="alert(1)">Hello {{name}}</p>', 'design_json' => ['pages' => []]];
    }
}
