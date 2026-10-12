<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use App\Models\EmailOptOut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailUnsubscribeController extends Controller
{
    public function show(EmailCampaignRecipient $recipient): View
    {
        abort_unless($recipient->campaign->purpose === 'marketing', 404);

        return view('email-campaigns.unsubscribe', ['recipient' => $recipient]);
    }

    public function store(Request $request, EmailCampaignRecipient $recipient): RedirectResponse
    {
        abort_unless($recipient->campaign->purpose === 'marketing', 404);
        EmailOptOut::firstOrCreate(['email' => strtolower($recipient->email)]);

        return redirect()->to(url('/'))->with('success', 'You have been unsubscribed from future email campaigns.');
    }
}
