<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePauseCodeRequest;
use App\Http\Requests\Admin\UpdatePauseCodeRequest;
use App\Models\PauseCode;
use App\Services\PauseCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PauseCodesController extends Controller
{
    public function __construct(
        protected PauseCodeService $pauseCodeService
    ) {}

    public function index(Request $request): View
    {
        $codes = PauseCode::query()->orderBy('sort_order')->orderBy('code')->get();

        return view('admin.pause_codes', [
            'codes' => $codes,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StorePauseCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        PauseCode::query()->create([
            'code' => strtoupper($validated['code']),
            'label' => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->pauseCodeService->flushCache();

        return redirect()->route('admin.pause-codes.index')
            ->with('success', 'Pause code created.');
    }

    public function update(UpdatePauseCodeRequest $request, PauseCode $pauseCode): RedirectResponse
    {
        $validated = $request->validated();
        $pauseCode->update([
            'code' => strtoupper($validated['code']),
            'label' => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? $pauseCode->sort_order,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $pauseCode->is_active,
        ]);
        $this->pauseCodeService->flushCache();

        return redirect()->route('admin.pause-codes.index')
            ->with('success', 'Pause code updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (! $request->user()?->isTeamLeader()) {
            abort(403);
        }
        $request->validate([
            'id' => ['required', 'integer', 'exists:pause_codes,id'],
        ]);
        PauseCode::query()->whereKey($request->input('id'))->delete();
        $this->pauseCodeService->flushCache();

        return redirect()->route('admin.pause-codes.index')
            ->with('success', 'Pause code removed.');
    }
}
