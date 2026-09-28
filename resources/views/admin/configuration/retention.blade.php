            @php
                $selectedRetentionForm = collect($retentionForms ?? [])->firstWhere('id', (int) ($selectedRetentionFormId ?? 0));
                $selectedRetentionPolicy = $selectedRetentionForm?->retentionPolicy;
                $retentionFromDate = old('from_date', $selectedRetentionPolicy?->from_date?->format('Y-m-d') ?? '');
                $retentionToDate = old('to_date', $selectedRetentionPolicy?->to_date?->format('Y-m-d') ?? '');
                $retentionIsActive = old('is_active', $selectedRetentionPolicy?->is_active ?? true);
                $retentionDeletionMode = old('deletion_mode', $selectedRetentionPolicy?->deletion_mode ?? 'whole_record');
                $retentionSelectedFields = old('selected_fields', $selectedRetentionPolicy?->selected_fields ?? []);
                $retentionSelectedFields = is_array($retentionSelectedFields) ? $retentionSelectedFields : [];
                $retentionRunMode = old('run_mode', $selectedRetentionPolicy?->run_mode ?? 'recurring');
                $retentionRunAt = old('run_at', $selectedRetentionPolicy?->run_at?->format('Y-m-d\\TH:i') ?? '');
                $retentionRecurrence = old('recurrence', $selectedRetentionPolicy?->recurrence ?? 'daily');
                $retentionRunTime = old('run_time', $selectedRetentionPolicy?->run_time ? substr((string) $selectedRetentionPolicy->run_time, 0, 5) : '03:00');
                $retentionRunDayOfWeek = old('run_day_of_week', $selectedRetentionPolicy?->run_day_of_week ?? 1);
                $retentionRunDayOfMonth = old('run_day_of_month', $selectedRetentionPolicy?->run_day_of_month ?? 1);
            @endphp

            <div class="space-y-6" x-data="{ deletionMode: @js($retentionDeletionMode), executionMode: @js($retentionRunMode), recurrence: @js($retentionRecurrence) }">
                <div x-show="deletionMode === 'whole_record'" x-cloak>
                    <x-alert type="warning" title="Permanent deletion">
                        Retention cleanup permanently deletes complete records from the selected form when their record date is within the configured From and To dates. This cannot be undone.
                    </x-alert>
                </div>
                <div x-show="deletionMode === 'selected_fields'" x-cloak>
                    <x-alert type="warning" title="Permanent field clearing">
                        Retention cleanup permanently clears the selected field values from records within the configured From and To dates. The records and unselected fields are preserved, but cleared values cannot be recovered.
                    </x-alert>
                </div>

                <form method="GET" action="{{ route('admin.configuration') }}" class="md-card md-card--static">
                    <input type="hidden" name="tab" value="retention">
                    <div class="p-4">
                        <div class="form-field max-w-xl">
                            <label class="form-label" for="retention-form-filter">Form</label>
                            <select id="retention-form-filter" name="retention_form" class="form-select" @change="$el.form.submit()">
                                @forelse($retentionForms ?? [] as $retentionForm)
                                    <option value="{{ $retentionForm->id }}" @selected((int) $selectedRetentionFormId === $retentionForm->id)>
                                        {{ $retentionForm->campaign_code }} — {{ $retentionForm->name }}
                                    </option>
                                @empty
                                    <option value="">No active forms configured</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                </form>

                @if($selectedRetentionForm)
                    <form method="POST" action="{{ route('admin.configuration.retention.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="form_id" value="{{ $selectedRetentionForm->id }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="form-field sm:col-span-2">
                                <span class="form-label">Deletion scope</span>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="deletion_mode" value="whole_record" x-model="deletionMode" @checked($retentionDeletionMode === 'whole_record') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Delete entire records</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Remove every field from matching form records.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="deletion_mode" value="selected_fields" x-model="deletionMode" @checked($retentionDeletionMode === 'selected_fields') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Clear selected fields only</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Preserve records and all fields that are not selected.</span>
                                        </span>
                                    </label>
                                </div>
                                @error('deletion_mode')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="retention-from-date">From date</label>
                                <input id="retention-from-date" type="date" name="from_date" value="{{ $retentionFromDate }}" class="form-input @error('from_date') error @enderror" required>
                                @error('from_date')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="retention-to-date">To date</label>
                                <input id="retention-to-date" type="date" name="to_date" value="{{ $retentionToDate }}" class="form-input @error('to_date') error @enderror" required>
                                @error('to_date')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-field sm:col-span-2 flex items-end pb-1">
                                <label class="checkbox-row">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($retentionIsActive)>
                                    <span>Active automatic cleanup</span>
                                </label>
                            </div>
                            <div class="form-field sm:col-span-2 rounded-lg border border-[var(--color-border)] p-4 space-y-4">
                                <div>
                                    <span class="form-label">Execution mode</span>
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Run Once supports a scheduled date/time and the Run Now action. Recurring policies can run daily, weekly, or monthly.</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="run_mode" value="once" x-model="executionMode" @checked($retentionRunMode === 'once') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Run Once</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Schedule one automatic execution or run it immediately from the policy list.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="run_mode" value="recurring" x-model="executionMode" @checked($retentionRunMode === 'recurring') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Recurring</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Keep the policy active and run it on a daily, weekly, or monthly schedule.</span>
                                        </span>
                                    </label>
                                </div>
                                @error('run_mode')<p class="form-error">{{ $message }}</p>@enderror

                                <div x-show="executionMode === 'once'" x-cloak class="form-field">
                                    <label class="form-label" for="retention-run-at">Scheduled run date and time</label>
                                    <input id="retention-run-at" type="datetime-local" name="run_at" value="{{ $retentionRunAt }}" class="form-input @error('run_at') error @enderror" x-bind:disabled="executionMode !== 'once'">
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Use Run Now below when this one-time policy should execute immediately.</p>
                                    @error('run_at')<p class="form-error">{{ $message }}</p>@enderror
                                </div>

                                <div x-show="executionMode === 'recurring'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-field">
                                        <label class="form-label" for="retention-recurrence">Frequency</label>
                                        <select id="retention-recurrence" name="recurrence" class="form-select" x-model="recurrence" x-bind:disabled="executionMode !== 'recurring'">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                        @error('recurrence')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label" for="retention-run-time">Run time</label>
                                        <input id="retention-run-time" type="time" name="run_time" value="{{ $retentionRunTime }}" class="form-input @error('run_time') error @enderror" x-bind:disabled="executionMode !== 'recurring'">
                                        @error('run_time')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div x-show="recurrence === 'weekly'" x-cloak class="form-field">
                                        <label class="form-label" for="retention-run-day-of-week">Day of week</label>
                                        <select id="retention-run-day-of-week" name="run_day_of_week" class="form-select" x-bind:disabled="executionMode !== 'recurring' || recurrence !== 'weekly'">
                                            @foreach([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $dayValue => $dayLabel)
                                                <option value="{{ $dayValue }}" @selected((int) $retentionRunDayOfWeek === $dayValue)>{{ $dayLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('run_day_of_week')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div x-show="recurrence === 'monthly'" x-cloak class="form-field">
                                        <label class="form-label" for="retention-run-day-of-month">Day of month</label>
                                        <select id="retention-run-day-of-month" name="run_day_of_month" class="form-select" x-bind:disabled="executionMode !== 'recurring' || recurrence !== 'monthly'">
                                            @foreach(range(1, 31) as $dayValue)
                                                <option value="{{ $dayValue }}" @selected((int) $retentionRunDayOfMonth === $dayValue)>{{ $dayValue }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">If a month is shorter, the last day of that month is used.</p>
                                        @error('run_day_of_month')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div x-show="deletionMode === 'selected_fields'" x-cloak class="rounded-lg border border-[var(--color-border)] p-4">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Fields to clear</h3>
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Choose one or more fields. The record and unselected fields will remain.</p>
                                </div>
                                <span class="text-xs text-[var(--color-on-surface-dim)]">{{ $selectedRetentionForm->form_code }}</span>
                            </div>
                            @if($selectedRetentionForm->formFields->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    @foreach($selectedRetentionForm->formFields as $field)
                                        <label class="flex items-start gap-3 rounded-md border border-[var(--color-border)] p-3 cursor-pointer">
                                            <input type="checkbox"
                                                   name="selected_fields[]"
                                                   value="{{ $field->field_name }}"
                                                   @checked(in_array($field->field_name, $retentionSelectedFields, true))
                                                   x-bind:disabled="deletionMode !== 'selected_fields'"
                                                   class="mt-1 h-4 w-4 rounded border-[var(--color-border)]">
                                            <span class="min-w-0">
                                                <span class="block text-sm text-[var(--color-on-surface)]">{{ $field->field_label }}</span>
                                                <span class="block text-xs font-mono text-[var(--color-on-surface-dim)] truncate">{{ $field->field_name }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-[var(--color-on-surface-dim)]">No eligible fields are registered for this form.</p>
                            @endif
                            @error('selected_fields')<p class="form-error mt-3">{{ $message }}</p>@enderror
                        </div>
                        @error('form_id')<p class="form-error">{{ $message }}</p>@enderror
                        <div>
                            <button type="submit" class="btn-primary">Save Retention Policy</button>
                        </div>
                    </form>
                @endif

                <div>
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-3">Configured policies</h3>
                    <x-table.index caption="Configured data retention policies">
                        <x-table.head :columns="[
                            ['label' => 'Campaign'],
                            ['label' => 'Form'],
                            ['label' => 'Storage table'],
                            ['label' => 'Deletion scope'],
                            ['label' => 'Selected fields'],
                            ['label' => 'From date'],
                            ['label' => 'To date'],
                            ['label' => 'Execution'],
                            ['label' => 'Next run'],
                            ['label' => 'Last run'],
                            ['label' => 'Result'],
                            ['label' => 'Actions', 'align' => 'right'],
                        ]" />
                        <tbody>
                            @forelse($retentionPolicies ?? [] as $policy)
                                <tr>
                                    <td>{{ $policy->form?->campaign?->name ?? $policy->form?->campaign_code ?? '—' }}</td>
                                    <td>{{ $policy->form?->name ?? 'Form unavailable' }}</td>
                                    <td class="font-mono text-xs">{{ $policy->form?->table_name ?? '—' }}</td>
                                    <td>{{ $policy->deletion_mode === 'selected_fields' ? 'Clear selected fields' : 'Delete entire records' }}</td>
                                    <td class="max-w-xs text-xs">{{ $policy->selected_fields ? implode(', ', $policy->selected_fields) : '—' }}</td>
                                    <td>{{ $policy->from_date?->format('Y-m-d') ?? 'Any date' }}</td>
                                    <td>{{ $policy->to_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        <x-badge :type="$policy->is_active ? 'active' : 'inactive'">
                                            {{ $policy->is_active ? 'Active' : 'Inactive' }}
                                        </x-badge>
                                        <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">
                                            {{ $policy->run_mode === 'once' ? 'Run Once' : 'Recurring '.ucfirst($policy->recurrence ?? 'daily') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $policy->next_run_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td>{{ $policy->last_run_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                    <td>
                                        <span class="block">{{ $policy->last_run_status ? ucfirst($policy->last_run_status) : 'Not run' }}</span>
                                        <span class="block text-xs text-[var(--color-on-surface-dim)]">{{ number_format($policy->last_deleted_count) }} affected</span>
                                        @if($policy->last_error)
                                            <span class="block max-w-xs text-xs text-red-500 truncate" title="{{ $policy->last_error }}">{{ $policy->last_error }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-actions justify-end flex-wrap">
                                            @if($policy->form)
                                                <a href="{{ route('admin.configuration', ['tab' => 'retention', 'retention_form' => $policy->form_id]) }}" class="btn-secondary text-xs px-2 py-1">Edit</a>
                                            @endif
                                            @if($policy->is_active)
                                                <form method="POST" action="{{ route('admin.configuration.retention.run', $policy) }}" onsubmit="return confirm('Run this retention policy now? This may permanently delete or clear matching data.')">
                                                    @csrf
                                                    <button type="submit" class="btn-danger text-xs px-2 py-1">Run Now</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.configuration.retention.destroy', $policy) }}" onsubmit="return confirm('Delete only this retention policy configuration? Form data will not be changed.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger text-xs px-2 py-1">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-table.empty :colspan="13" message="No retention policies configured." description="Choose an active form, date range, and execution schedule above to begin." />
                            @endforelse
                        </tbody>
                    </x-table.index>
                </div>
            </div>
