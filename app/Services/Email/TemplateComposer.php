<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use DOMDocument;
use DOMElement;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Validation\ValidationException;

class TemplateComposer
{
    public const VARIABLES = ['name', 'email', 'employee_id', 'period'];

    public function sanitize(string $html): string
    {
        if (strlen($html) > 200000 || ! mb_check_encoding($html, 'UTF-8') || preg_match('/<!DOCTYPE|<!ENTITY/i', $html)) {
            $this->invalid('Email HTML is invalid or exceeds 200 KB.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (str_contains($attribute->value, '{{') || str_contains($attribute->value, '}}')) {
                    $this->invalid('Merge tags are allowed in visible text only, never in attributes, styles or URLs.');
                }
                if (strtolower($attribute->name) === 'style' && preg_match('/url\s*\(|@import|expression\s*\(/i', $attribute->value)) {
                    $this->invalid('Remote CSS, background URLs and executable styles are not supported.');
                }
            }
            if (in_array(strtolower($element->tagName), ['style', 'script'], true) && str_contains($element->textContent, '{{')) {
                $this->invalid('Merge tags are allowed in visible text only.');
            }
            if (strtolower($element->tagName) === 'img') {
                $this->validateImage($element);
            }
        }

        $this->validateVariables($html);
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'div[style],p[style],span[style],h1[style],h2[style],h3[style],h4[style],strong,b,em,i,u,s,br,hr[style],ul[style],ol[style],li[style],blockquote[style],table[width|align|border|cellpadding|cellspacing|style],thead,tbody,tfoot,tr[style],td[colspan|rowspan|width|height|align|valign|style],th[colspan|rowspan|width|height|align|valign|style],a[href|title|style],img[src|alt|width|height|style]');
        $config->set('CSS.AllowedProperties', ['color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'text-align', 'line-height', 'letter-spacing', 'width', 'height', 'max-width', 'min-width', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-color', 'border-width', 'border-style', 'border-collapse', 'vertical-align', 'list-style-type']);
        $config->set('URI.AllowedSchemes', ['https' => true, 'http' => true, 'mailto' => true]);
        $config->set('URI.DisableExternalResources', false);
        $clean = (new HTMLPurifier($config))->purify($html);
        if (trim(strip_tags($clean)) === '' && ! str_contains($clean, '<img')) {
            $this->invalid('Add readable email content before saving.');
        }

        return $clean;
    }

    public function render(EmailTemplate $template, array $values): string
    {
        $body = (string) $template->html_body;
        if (($template->body_format ?? 'text') !== 'html') {
            return '<div>'.nl2br(e($this->personalize($body, $values))).'</div>';
        }

        $safe = $this->sanitize($body);

        return $this->personalize($safe, $values, true);
    }

    public function subject(EmailTemplate $template, array $values): string
    {
        $subject = $this->personalize((string) $template->subject, $values);
        if (preg_match('/[\r\n\x00]/', $subject)) {
            $this->invalid('The subject and its merge values must not contain line breaks.');
        }

        return $subject;
    }

    public function snapshot(EmailTemplate $template): array
    {
        $snapshot = $template->only(['name', 'subject', 'html_body', 'pdf_enabled', 'pdf_body', 'pdf_password']);
        $snapshot['body_format'] = $template->body_format ?? 'text';
        $this->validateVariables((string) $template->subject);
        if ($snapshot['body_format'] === 'html') {
            $snapshot['html_body'] = $this->sanitize((string) $template->html_body);
        }

        return $snapshot;
    }

    public function validateVariables(string $text): void
    {
        preg_match_all('/\{\{\s*([^{}]+)\s*\}\}/', $text, $matches);
        foreach ($matches[1] as $variable) {
            if (! in_array(trim($variable), self::VARIABLES, true)) {
                $this->invalid('Supported merge tags: name, email, employee_id and period.');
            }
        }
        $without = preg_replace('/\{\{\s*([^{}]+)\s*\}\}/', '', $text);
        if (str_contains($without, '{{') || str_contains($without, '}}')) {
            $this->invalid('A merge tag is malformed. Use {{name}} or another supported variable.');
        }
    }

    private function personalize(string $text, array $values, bool $escape = false): string
    {
        $this->validateVariables($text);

        return preg_replace_callback('/\{\{\s*([^{}]+)\s*\}\}/', function (array $matches) use ($values, $escape): string {
            $key = trim($matches[1]);
            $value = $values[$key] ?? null;
            if (blank($value) && $key !== 'name') {
                $this->invalid('Missing recipient value for merge tag: '.$key.'.');
            }
            $value = (string) (blank($value) ? 'Recipient' : $value);

            return $escape ? e($value) : $value;
        }, $text);
    }

    private function validateImage(DOMElement $image): void
    {
        $source = $image->getAttribute('src');
        $url = parse_url($source);
        $hosts = array_map('strtolower', (array) config('email_campaigns.image_hosts', []));
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($url['host'] ?? ''), $hosts, true)
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || filter_var($url['host'] ?? '', FILTER_VALIDATE_IP)
            || ! preg_match('/\.(png|jpe?g|gif|webp)$/i', $url['path'] ?? '')) {
            $this->invalid('Images require a configured public HTTPS raster-image host and a PNG, JPEG, GIF or WebP path.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['html_body' => $message]);
    }
}
