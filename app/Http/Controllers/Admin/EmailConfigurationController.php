<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSmtpSetting;
use App\Services\EmailSmtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class EmailConfigurationController extends Controller
{
    public function index(EmailSmtpService $smtp): View
    {
        return view('admin.email-configuration', [
            'settings' => $smtp->settings(),
            'mailSource' => $smtp->source(),
            'smtpReady' => $smtp->isReady(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'host' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.\-\[\]:]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'from_name' => ['required', 'string', 'max:255'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
        ]);

        // Empty password means keep the currently saved credential. Never put it
        // back into request input or flash it to the session on validation errors.
        $settings = EmailSmtpSetting::query()->first();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if (blank($data['username'] ?? null)) {
            $data['username'] = null;
            $data['password'] = null;
        }

        if ($settings) {
            $settings->update($data);
        } else {
            EmailSmtpSetting::create($data);
        }

        return redirect()->route('admin.email-configuration.index')
            ->with('success', 'Email configuration saved. New campaign deliveries will use these settings.');
    }

    public function test(Request $request, EmailSmtpService $smtp): RedirectResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:255'],
        ]);

        if (! $smtp->isReady()) {
            return back()->with('error', 'Configure and enable an SMTP connection before sending a test email.');
        }

        try {
            [$fromAddress, $fromName] = $smtp->sender();
            $smtp->mailer()->raw(
                'Your CRM email connection is configured and this test message was accepted by the outgoing mail server.',
                static function (Message $message) use ($data, $fromAddress, $fromName): void {
                    $message->from($fromAddress, $fromName)
                        ->to($data['recipient'])
                        ->subject('CRM SMTP test');
                },
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Test email could not be sent. Check the SMTP address, port, security setting, credentials, and server logs.');
        }

        return back()->with('success', 'Test email accepted by the configured mail server. Check the recipient inbox.');
    }
}
