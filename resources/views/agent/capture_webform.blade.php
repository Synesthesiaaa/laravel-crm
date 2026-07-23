<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');
        })();
    </script>
    <title>{{ $campaignName }} - Agent Capture</title>
    @vite(['resources/css/app.css', 'resources/js/agent-capture-webform-entry.js'])
</head>
<body class="min-h-screen bg-[var(--color-surface)]">
<main class="mx-auto w-full max-w-3xl p-4 sm:p-6">
    <header class="mb-5">
        <p class="text-xs uppercase tracking-wider text-[var(--color-on-surface-dim)]">{{ $campaignName }}</p>
        @if($configuration)
            <h1 class="mt-1 text-xl font-semibold text-[var(--color-on-surface)]">{{ $configuration['form']->name }}</h1>
        @else
            <h1 class="mt-1 text-xl font-semibold text-[var(--color-on-surface)]">Agent Capture</h1>
        @endif
    </header>

    @if($configuration && $configuration['fields']->isNotEmpty())
        <form id="agent-capture-webform"
              method="POST"
              action="{{ route('api.agent.capture') }}"
              novalidate
              x-data="agentCaptureWebform(@js([
                  'campaignCode' => $campaignCode,
                  'leadId' => $prefill['lead_id'],
                  'phoneNumber' => $prefill['phone_number'],
                  'values' => $prefill['fields'],
              ]))"
              x-init="init(values)"
              @submit.prevent="submitCapture()"
              class="space-y-5">
            @csrf
            <input type="hidden" name="campaign_code" value="{{ $campaignCode }}" x-model="campaignCode">
            <input type="hidden" name="lead_id" value="{{ $prefill['lead_id'] ?? '' }}" x-model="leadId">
            <input type="hidden" name="phone_number" value="{{ $prefill['phone_number'] ?? '' }}" x-model="phoneNumber">

            <section class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold text-[var(--color-on-surface)]">Capture Details</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach($configuration['fields'] as $field)
                        @php
                            $fieldKey = (string) $field->field_key;
                            $fieldType = (string) ($field->field_type ?: 'text');
                            $fieldValue = $prefill['fields'][$fieldKey] ?? '';
                            $visibility = $field->visibility ?: null;
                        @endphp
                        <div data-capture-field
                             class="{{ $field->field_width === 'full' ? 'sm:col-span-2' : '' }}"
                            x-show="shouldShow('{{ $fieldKey }}', @js($visibility))"
                             x-cloak>
                            <label class="form-label" for="capture_{{ $fieldKey }}">
                                {{ $field->field_label }}
                                @if($field->is_required)<span class="ml-0.5 text-[var(--color-danger)]">*</span>@endif
                            </label>
                            @if($fieldType === 'textarea')
                                <textarea id="capture_{{ $fieldKey }}" name="{{ $fieldKey }}" rows="3"
                                          class="form-textarea"
                                          x-model="values['{{ $fieldKey }}']"
                                          @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                                          @if($field->is_required) x-bind:required="shouldShow('{{ $fieldKey }}', @js($visibility))" @endif>{{ $fieldValue }}</textarea>
                            @elseif($fieldType === 'select')
                                <select id="capture_{{ $fieldKey }}" name="{{ $fieldKey }}" class="form-select"
                                        x-model="values['{{ $fieldKey }}']"
                                        @if($field->is_required) x-bind:required="shouldShow('{{ $fieldKey }}', @js($visibility))" @endif>
                                    <option value="">-- Select --</option>
                                    @foreach(($field->options ?? []) as $option)
                                        <option value="{{ $option }}" @selected((string) $fieldValue === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif($fieldType === 'percentage')
                                <div class="relative">
                                    <input id="capture_{{ $fieldKey }}" type="number" min="0" max="100" step="0.01"
                                           name="{{ $fieldKey }}" class="form-input pr-8"
                                           value="{{ $fieldValue }}"
                                           x-model="values['{{ $fieldKey }}']"
                                           @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                                           @if($field->is_required) x-bind:required="shouldShow('{{ $fieldKey }}', @js($visibility))" @endif>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[var(--color-on-surface-dim)]">%</span>
                                </div>
                            @elseif($fieldType === 'checkbox')
                                <label class="flex items-center gap-2 pt-2 text-sm text-[var(--color-on-surface)]">
                                    <input id="capture_{{ $fieldKey }}" type="checkbox" name="{{ $fieldKey }}" value="1"
                                           class="h-4 w-4 accent-[var(--color-primary)]"
                                           x-model="values['{{ $fieldKey }}']">
                                    <span>{{ $field->placeholder ?: 'Yes' }}</span>
                                </label>
                            @else
                                <input id="capture_{{ $fieldKey }}"
                                       type="{{ $fieldType === 'number' ? 'text' : $fieldType }}"
                                       name="{{ $fieldKey }}" class="form-input"
                                       value="{{ $fieldValue }}"
                                       x-model="values['{{ $fieldKey }}']"
                                       @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                                       @if($field->is_required) x-bind:required="shouldShow('{{ $fieldKey }}', @js($visibility))" @endif>
                            @endif
                            <p x-show="errors['{{ $fieldKey }}']" x-text="errors['{{ $fieldKey }}']"
                               class="mt-1 text-xs text-red-600"></p>
                        </div>
                    @endforeach
                </div>
            </section>

            <div x-show="feedback.message" x-text="feedback.message"
                 class="rounded-lg border p-3 text-sm"
                 :class="feedback.success ? 'border-emerald-500/40 text-emerald-600' : 'border-red-500/40 text-red-600'"
                 role="status"></div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="saving">
                    <span x-text="saving ? 'Saving...' : 'Save Capture'">Save Capture</span>
                </button>
            </div>
        </form>
    @else
        <section class="rounded-xl border border-dashed border-[var(--color-border)] p-8 text-center">
            <p class="text-sm text-[var(--color-on-surface-dim)]">No web form is configured for this campaign.</p>
        </section>
    @endif
</main>
</body>
</html>
