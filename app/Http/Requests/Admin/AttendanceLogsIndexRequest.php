<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceLogsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeamLeader() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'event' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array{user_id: ?int, date: ?string, event: ?string}
     */
    public function attendanceFilters(): array
    {
        $validated = $this->validated();
        $event = isset($validated['event']) ? trim((string) $validated['event']) : '';

        return [
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'date' => isset($validated['date']) && $validated['date'] !== '' ? (string) $validated['date'] : null,
            'event' => $event !== '' ? $event : null,
        ];
    }
}
