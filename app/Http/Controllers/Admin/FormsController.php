<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Form;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormsController extends Controller
{
    public function __construct(protected CampaignService $campaignService) {}

    public function index(Request $request): View
    {
        $campaigns = Campaign::where('is_active', true)->orderBy('display_order')->get();
        $selectedCampaign = $request->query('campaign', $campaigns->first()?->code ?? '');
        $forms = Form::where('campaign_code', $selectedCampaign)->orderBy('display_order')->orderBy('id')->get();
        return view('admin.forms', [
            'campaigns' => $campaigns,
            'forms' => $forms,
            'selectedCampaign' => $selectedCampaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_code' => 'required|string|max:50|exists:campaigns,code',
            'form_code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'name' => 'required|string|max:255',
            'table_name' => 'required|string|max:100',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'display_order' => 'nullable|integer',
        ], [
            'form_code.required' => 'Form code is required.',
            'form_code.regex' => 'Form code may only contain lowercase letters, numbers, and underscores.',
            'name.required' => 'Form name is required.',
            'table_name.required' => 'Table name is required.',
        ]);
        $exists = Form::where('campaign_code', $validated['campaign_code'])->where('form_code', $validated['form_code'])->exists();
        if ($exists) {
            return back()->with('error', 'Form code already exists for this campaign.');
        }
        Form::create([
            'campaign_code' => $validated['campaign_code'],
            'form_code' => $validated['form_code'],
            'name' => $validated['name'],
            'table_name' => $validated['table_name'],
            'color' => $validated['color'] ?? 'blue',
            'icon' => $validated['icon'] ?? 'form',
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.forms.index', ['campaign' => $validated['campaign_code']])->with('success', 'Form created.');
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_code' => 'required|string|max:50|exists:campaigns,code',
            'form_code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'name' => 'required|string|max:255',
            'table_name' => 'required|string|max:100',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'display_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ], [
            'form_code.regex' => 'Form code may only contain lowercase letters, numbers, and underscores.',
            'name.required' => 'Form name is required.',
            'table_name.required' => 'Table name is required.',
        ]);
        $exists = Form::where('campaign_code', $validated['campaign_code'])->where('form_code', $validated['form_code'])->where('id', '!=', $form->id)->exists();
        if ($exists) {
            return back()->with('error', 'Form code already exists for this campaign.');
        }
        $form->update([
            'campaign_code' => $validated['campaign_code'],
            'form_code' => $validated['form_code'],
            'name' => $validated['name'],
            'table_name' => $validated['table_name'],
            'color' => $validated['color'] ?? 'blue',
            'icon' => $validated['icon'] ?? 'form',
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.forms.index', ['campaign' => $form->campaign_code])->with('success', 'Form updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $form = Form::findOrFail((int) $request->input('id'));
        $form->update(['is_active' => false]);
        $this->campaignService->clearCampaignsCache();
        return redirect()->route('admin.forms.index', ['campaign' => $form->campaign_code])->with('success', 'Form deactivated.');
    }
}
