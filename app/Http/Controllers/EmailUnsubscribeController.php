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
        return view('email-campaigns.unsubscribe', ['recipient' => $recipient]);
    }

    public function store(Request $request, EmailCampaignRecipient $recipient): RedirectResponse
    {
        EmailOptOut::firstOrCreate(['email' => strtolower($recipient->email)]);

        return redirect()->to(url('/'))->with('success', 'You have been unsubscribed from future email campaigns.');
    }
}
