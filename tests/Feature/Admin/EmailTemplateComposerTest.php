<?php

namespace Tests\Feature\Admin;

use App\Models\EmailTemplate;
use App\Services\Email\TemplateComposer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailTemplateComposerTest extends TestCase
{
    public function test_legacy_messages_remain_escaped_plain_text(): void
    {
        $template = new EmailTemplate(['html_body' => "Hello {{name}}\n<strong>Original</strong>"]);
        $html = app(TemplateComposer::class)->render($template, ['name' => '<script>alert(1)</script>']);

        $this->assertStringContainsString('&lt;strong&gt;Original&lt;/strong&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<br', $html);
    }

    public function test_html_preserves_safe_email_layout_and_escapes_personalization(): void
    {
        $template = (new EmailTemplate)->forceFill(['body_format' => 'html', 'html_body' => '<table width="600"><tr><td style="color:#333333">Hello {{name}}</td></tr></table>']);
        $html = app(TemplateComposer::class)->render($template, ['name' => '<img src=x onerror=alert(1)>']);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_script_handlers_forms_and_active_links_are_removed(): void
    {
        $html = app(TemplateComposer::class)->sanitize('<script>alert(1)</script><form><input name="x"></form><p onclick="alert(1)">Safe <a href="javascript:alert(1)">link</a></p>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('<input', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('Safe', $html);
    }

    public function test_merge_tags_in_attributes_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(TemplateComposer::class)->sanitize('<a href="https://example.com/{{email}}">Read</a>');
    }

    public function test_merge_tags_in_styles_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(TemplateComposer::class)->sanitize('<style>.x {color: {{name}}}</style><p>Safe</p>');
    }

    public function test_remote_images_require_configured_https_raster_host(): void
    {
        config(['email_campaigns.image_hosts' => ['images.example.com']]);
        $html = app(TemplateComposer::class)->sanitize('<img alt="Logo" src="https://images.example.com/logo.png">');
        $this->assertStringContainsString('https://images.example.com/logo.png', $html);

        $this->expectException(ValidationException::class);
        app(TemplateComposer::class)->sanitize('<img src="https://images.example.com/logo.svg">');
    }

    public function test_unconfigured_image_hosts_fail_closed(): void
    {
        config(['email_campaigns.image_hosts' => []]);
        $this->expectException(ValidationException::class);
        app(TemplateComposer::class)->sanitize('<img src="https://images.example.com/logo.png">');
    }

    public function test_unknown_or_missing_merge_variables_fail_validation(): void
    {
        $this->expectException(ValidationException::class);
        app(TemplateComposer::class)->render(new EmailTemplate(['html_body' => 'Period {{period}}']), ['name' => 'A']);
    }

    public function test_snapshot_records_format_without_builder_state(): void
    {
        $template = (new EmailTemplate)->forceFill(['name' => 'Reusable', 'subject' => 'Hello {{name}}', 'html_body' => '<p>Hello {{name}}</p>', 'body_format' => 'html', 'design_json' => ['pages' => []], 'pdf_enabled' => false]);
        $snapshot = app(TemplateComposer::class)->snapshot($template);

        $this->assertSame('html', $snapshot['body_format']);
        $this->assertSame('<p>Hello {{name}}</p>', $snapshot['html_body']);
        $this->assertArrayNotHasKey('design_json', $snapshot);
    }
}
