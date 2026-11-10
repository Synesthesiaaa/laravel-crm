<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Services\Email\EmailTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    public function open(Request $request, string $token, EmailTrackingService $tracking): Response
    {
        $tracking->observe($tracking->resolve($token, 'open'), $this->private($request));

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type' => 'image/gif', 'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function click(Request $request, string $token, EmailTrackingService $tracking): RedirectResponse
    {
        $record = $tracking->resolve($token, 'click');
        abort_unless($tracking->safeDestination((string) $record->destination), 410);
        $tracking->observe($record, $this->private($request));

        return redirect()->away($record->destination)->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'private, no-store']);
    }

    public function showUnsubscribe(string $token, EmailTrackingService $tracking): Response
    {
        $tracking->resolve($token, 'unsubscribe');

        return response()->view('email-campaigns.token-unsubscribe', ['token' => $token, 'completed' => false])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function optOut(string $token, EmailTrackingService $tracking): Response
    {
        $tracking->unsubscribe($tracking->resolve($token, 'unsubscribe'));

        return response()->view('email-campaigns.token-unsubscribe', ['token' => $token, 'completed' => true])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function private(Request $request): bool
    {
        return $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1';
    }
}
