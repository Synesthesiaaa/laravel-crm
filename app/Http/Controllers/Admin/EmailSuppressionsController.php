<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailSuppressionRequest;
use App\Models\EmailSuppression;
use App\Services\Email\SuppressionService;
use Illuminate\Http\Request;

class EmailSuppressionsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'reason' => ['nullable', 'in:manual,hard_bounce,complaint,unsubscribe']]);
        $suppressions = EmailSuppression::query()
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('email', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'))
            ->when($data['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.email-suppressions.index', compact('suppressions'));
    }

    public function store(StoreEmailSuppressionRequest $request, SuppressionService $suppression)
    {
        $data = $request->validated();
        $record = $suppression->suppress($data['email'], 'manual', $data['purpose'], 'manual', $request->user()->id);
        activity('email-campaign')->causedBy($request->user())->event('suppression-added')
            ->withProperties(['suppression_id' => $record->id, 'purpose' => $record->purpose])->log('email_campaign.suppression_added');

        return back()->with('success', 'Manual email suppression saved.');
    }

    public function destroy(Request $request, EmailSuppression $suppression)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless($suppression->reason === 'manual', 409, 'Provider and unsubscribe suppressions require separate verified consent/provider review.');
        $id = $suppression->id;
        $suppression->delete();
        activity('email-campaign')->causedBy($request->user())->event('suppression-removed')
            ->withProperties(['suppression_id' => $id])->log('email_campaign.suppression_removed');

        return back()->with('success', 'Manual suppression removed. Existing opt-outs and provider suppressions still apply.');
    }
}
