<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStatusType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceStatusTypesController extends Controller
{
    public function index(Request $request): View
    {
        $types = AttendanceStatusType::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.attendance_status_types', [
            'types' => $types,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', 'unique:attendance_status_types,code'],
            'label' => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        AttendanceStatusType::create([
            'code' => $validated['code'],
            'label' => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('admin.attendance-statuses.index')
            ->with('success', __('Attendance status created.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $type = AttendanceStatusType::findOrFail($id);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('attendance_status_types', 'code')->ignore($type->id),
            ],
            'label' => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $type->update([
            'code' => $validated['code'],
            'label' => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? $type->sort_order,
            'is_active' => $request->boolean('is_active', $type->is_active),
        ]);

        return redirect()->route('admin.attendance-statuses.index')
            ->with('success', __('Attendance status updated.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $type = AttendanceStatusType::findOrFail($id);
        $type->update(['is_active' => false]);

        return redirect()->route('admin.attendance-statuses.index')
            ->with('success', __('Attendance status deactivated.'));
    }
}
