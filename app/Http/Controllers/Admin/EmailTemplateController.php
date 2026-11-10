<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Services\Email\TemplateComposer;
use App\Services\EmailSmtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class EmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeActor($request);
        $query = EmailTemplate::query();
        if (! $request->user()->isSuperAdmin()) {
            $query->where('created_by_user_id', $request->user()->id)->where('pdf_enabled', false)
                ->whereDoesntHave('campaigns', fn ($campaigns) => $campaigns->where('purpose', '!=', 'marketing')->orWhere('attachment_mode', '!=', 'none'));
        }
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.mb_substr($search, 0, 150).'%');
        }

        return view('admin.email-templates.index', ['templates' => $query->latest()->paginate(15)->withQueryString(), 'search' => $search]);
    }

    public function create(Request $request): View
    {
        $this->authorizeActor($request);

        return view('admin.email-templates.edit', ['template' => new EmailTemplate(['name' => '', 'subject' => '', 'html_body' => '', 'body_format' => 'html']), 'locked' => false]);
    }

    public function store(StoreEmailTemplateRequest $request, TemplateComposer $composer): RedirectResponse
    {
        $data = $this->data($request, $composer);
        $template = EmailTemplate::create([...$data, 'created_by_user_id' => $request->user()->id, 'pdf_enabled' => false]);
        $this->audit($request, $template, 'created');

        return redirect()->route('admin.email-templates.edit', $template)->with('success', 'Email template saved.');
    }

    public function edit(Request $request, EmailTemplate $template): View
    {
        $this->authorizeTemplate($request, $template);

        return view('admin.email-templates.edit', ['template' => $template, 'locked' => $this->locked($template)]);
    }

    public function update(StoreEmailTemplateRequest $request, EmailTemplate $template, TemplateComposer $composer): RedirectResponse
    {
        $this->authorizeTemplate($request, $template);
        if ($this->locked($template)) {
            throw ValidationException::withMessages(['template' => 'This template is used by an approved campaign. Create a new template for changes.']);
        }
        $template->update($this->data($request, $composer));
        $this->audit($request, $template, 'updated');

        return redirect()->route('admin.email-templates.edit', $template)->with('success', 'Email template updated.');
    }

    public function preview(Request $request, TemplateComposer $composer): JsonResponse
    {
        $this->authorizeActor($request);
        $data = $request->validate(['body_format' => ['required', 'in:text,html'], 'html_body' => ['required', 'string', 'max:200000']]);
        $template = (new EmailTemplate)->forceFill($data);

        return response()->json([
            'html' => $composer->render($template, $this->syntheticValues()),
            'source' => $data['body_format'] === 'html' ? $composer->sanitize($data['html_body']) : $data['html_body'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function test(Request $request, EmailTemplate $template, TemplateComposer $composer, EmailSmtpService $smtp): RedirectResponse
    {
        $this->authorizeTemplate($request, $template);
        $address = $request->user()->email;
        if (! filter_var($address, FILTER_VALIDATE_EMAIL) || ! $smtp->isReady()) {
            return back()->withErrors(['template_test' => 'Set a valid account email and configure SMTP before sending a template test.'], 'templateTest');
        }

        try {
            $values = $this->syntheticValues();
            $html = $composer->render($template, $values);
            $subject = '[CRM template test] '.$composer->subject($template, $values);
            [$fromAddress, $fromName] = $smtp->sender();
            $smtp->mailer()->html($html, static function (Message $message) use ($address, $subject, $fromAddress, $fromName): void {
                $message->from($fromAddress, $fromName)->to($address)->subject($subject);
            });
        } catch (Throwable) {
            return back()->withErrors(['template_test' => 'The rendered test could not be submitted. Check SMTP configuration and required merge values. Campaign approval is unchanged.'], 'templateTest');
        }
        $this->audit($request, $template, 'synthetic_test');

        return back()->with('success', 'Rendered synthetic template test accepted by the mail server for your account address. This does not confirm inbox delivery.');
    }

    private function data(StoreEmailTemplateRequest $request, TemplateComposer $composer): array
    {
        $data = $request->safe()->only(['name', 'subject', 'body_format', 'html_body', 'design_json']);
        if ($data['body_format'] === 'html') {
            $data['html_body'] = $composer->sanitize($data['html_body']);
        } else {
            $composer->validateVariables($data['html_body']);
            $data['design_json'] = null;
        }

        return $data;
    }

    private function authorizeActor(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function authorizeTemplate(Request $request, EmailTemplate $template): void
    {
        $this->authorizeActor($request);
        if (! $request->user()->isSuperAdmin()) {
            abort_unless((int) $template->created_by_user_id === (int) $request->user()->id && ! $template->pdf_enabled, 404);
            abort_if($template->campaigns()->where(fn ($q) => $q->where('purpose', '!=', 'marketing')->orWhere('attachment_mode', '!=', 'none'))->exists(), 404);
        }
        // Generated protected PDFs retain their dedicated Super Admin editor.
        abort_if($template->pdf_enabled, 403, 'Use the confidential template workflow to edit this template.');
    }

    private function locked(EmailTemplate $template): bool
    {
        return $template->campaigns()->whereNotIn('status', ['draft', 'cancelled'])->exists();
    }

    private function syntheticValues(): array
    {
        return ['name' => 'Sample Recipient', 'email' => 'sample@example.com', 'employee_id' => 'SAMPLE-001', 'period' => 'Sample period'];
    }

    private function audit(Request $request, EmailTemplate $template, string $event): void
    {
        activity('email-template')->causedBy($request->user())->performedOn($template)->event($event)->log('email_template.'.$event);
    }
}
