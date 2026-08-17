<div class="max-w-3xl mx-auto">
    <x-breadcrumbs :items="[$formName => null]" />

    <form action="{{ route('forms.store') }}" method="POST"
          x-data="formVisibility({ submitting: false, autosave: true })"
          x-init="init(@js($prefill ?? []))"
          @submit.prevent="openReview()"
          data-user-id="{{ auth()->id() }}"
          data-campaign="{{ $campaign }}"
          data-form-type="{{ $formType }}"
          data-lead-id="{{ $leadId ?? '' }}"
          data-phone-number="{{ $phoneNumber ?? '' }}">
        @csrf
        <input type="hidden" name="campaign"     value="{{ $campaign }}">
        <input type="hidden" name="form_type"    value="{{ $formType }}">
        <input type="hidden" name="lead_id"      value="{{ $leadId ?? '' }}">
        <input type="hidden" name="phone_number" value="{{ $phoneNumber ?? '' }}">

        <div class="md-hero mb-6">
            <h1 class="text-xl font-bold text-[var(--color-on-surface)]">{{ $formName }}</h1>
            <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">Fill out the details below for {{ $campaignName }}.</p>
        </div>

        <x-validation-errors />

        <div x-cloak
             x-show="saveMessage || saveErrors.length > 0"
             x-transition.opacity
             class="alert mb-4"
             :class="saveStatus === 'success' ? 'alert-success' : 'alert-error'"
             role="alert"
             aria-live="polite">
            <template x-if="saveStatus === 'success'">
                <x-icon name="check-circle" class="w-4 h-4 shrink-0 mt-0.5" />
            </template>
            <template x-if="saveStatus !== 'success'">
                <x-icon name="x-circle" class="w-4 h-4 shrink-0 mt-0.5" />
            </template>
            <div class="flex-1 min-w-0">
                <p class="font-semibold mb-0.5" x-text="saveStatus === 'success' ? 'Saved' : 'Save failed'"></p>
                <p class="text-sm" x-text="saveMessage"></p>
                <ul class="list-disc list-inside space-y-0.5 text-sm mt-2" x-show="saveErrors.length > 0">
                    <template x-for="(error, index) in saveErrors" :key="index">
                        <li x-text="error"></li>
                    </template>
                </ul>
            </div>
            <button type="button"
                    @click="clearFeedback()"
                    class="shrink-0 opacity-60 hover:opacity-100"
                    aria-label="Dismiss">
                <x-icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>

        {{-- System fields --}}
        <x-form.group title="Reference" cols="2">
            <div class="form-field">
                <span class="form-label">Request ID</span>
                <input type="hidden" name="request_id" value="">
                <p class="form-help text-[var(--color-on-surface-muted)] mt-1">A unique reference (ULID) is assigned when you save.</p>
            </div>
            <x-form.input name="date" type="date" label="Date" :value="$prefill['date'] ?? date('Y-m-d')" :readonly="auth()->user()?->role === \App\Models\User::ROLE_AGENT" required />
        </x-form.group>

        {{-- VICIdial / lead fields --}}
        @if (!empty($viciFields))
        <x-form.group title="Lead Information (VICIdial)" cols="2">
            @foreach ($viciFields as $field)
                @if (!in_array($field['name'], ['request_id', 'date', 'agent']))
                @php
                    $visibilityRule = \Illuminate\Support\Js::from($field['visibility'] ?? null);
                @endphp
                <div @if(($field['field_width'] ?? '') === 'full') class="md:col-span-2" @endif
                     x-show="shouldShow('{{ $field['name'] }}', {{ $visibilityRule }})">
                    @if(($field['type'] ?? 'text') === 'textarea')
                        <x-form.textarea :name="$field['name']" :label="$field['label']"
                            :value="$prefill[$field['name']] ?? ''"
                            :required="$field['required'] ?? false"
                            x-model="values['{{ $field['name'] }}']"
                            x-bind:disabled="!isVisible({{ $visibilityRule }})" />
                    @elseif(($field['type'] ?? 'text') === 'select')
                        <div class="form-field">
                            <label class="form-label">
                                {{ $field['label'] }}
                                @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                            </label>
                            <select name="{{ $field['name'] }}" class="form-select" x-model="values['{{ $field['name'] }}']" x-bind:disabled="!isVisible({{ $visibilityRule }})" @if($field['required'] ?? false) required @endif>
                                <option value="">-- Select --</option>
                                @foreach(($field['options'] ?? []) as $opt)
                                    @php
                                        $val     = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                                        $display = is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : $opt;
                                    @endphp
                                    <option value="{{ $val }}" @selected(($prefill[$field['name']] ?? '') == $val)>{{ $display }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif(($field['type'] ?? 'text') === 'multiselect')
                        @php
                            $viciMultiSel = [];
                            $viciRaw = $prefill[$field['name']] ?? '';
                            if (is_string($viciRaw) && $viciRaw !== '') {
                                $viciDec = json_decode($viciRaw, true);
                                $viciMultiSel = is_array($viciDec) ? $viciDec : [];
                            }
                        @endphp
                        <fieldset class="form-field min-w-0">
                            <legend class="form-label mb-2">
                                {{ $field['label'] }}
                                @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                            </legend>
                            <div class="flex flex-col gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-3">
                                @foreach(($field['options'] ?? []) as $opt)
                                    @php
                                        $val     = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                                        $display = is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : $opt;
                                        $cid = 'vici-ms-' . $field['name'] . '-' . md5((string) $val);
                                    @endphp
                                    <label class="flex items-center gap-2 cursor-pointer text-sm text-[var(--color-on-surface)]">
                                        <input type="checkbox" name="{{ $field['name'] }}[]" value="{{ $val }}" id="{{ $cid }}"
                                            class="rounded border-[var(--color-border)]"
                                            x-bind:disabled="!isVisible({{ $visibilityRule }})"
                                            @checked(in_array((string) $val, array_map('strval', $viciMultiSel), true))>
                                        <span>{{ $display }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @elseif(($field['type'] ?? 'text') === 'percentage')
                        <div class="form-field">
                            <label class="form-label">
                                {{ $field['label'] }}
                                @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                            </label>
                            <div class="relative">
                                <input type="number"
                                       name="{{ $field['name'] }}"
                                       min="0"
                                       max="100"
                                       step="0.01"
                                       class="form-input pr-8"
                                       x-model="values['{{ $field['name'] }}']"
                                       x-bind:disabled="!isVisible({{ $visibilityRule }})"
                                       @if($field['required'] ?? false) required @endif>
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-on-surface-dim)] text-sm">%</span>
                            </div>
                        </div>
                    @else
                        <x-form.input
                            :name="$field['name']"
                            :label="$field['label']"
                            :type="$field['type'] === 'number' ? 'text' : ($field['type'] ?? 'text')"
                            :value="$prefill[$field['name']] ?? ''"
                            :required="$field['required'] ?? false"
                            x-model="values['{{ $field['name'] }}']"
                            x-bind:disabled="!isVisible({{ $visibilityRule }})" />
                    @endif
                </div>
                @endif
            @endforeach
        </x-form.group>
        @endif

        {{-- Campaign-specific fields --}}
        <x-form.group :title="$formName . ' Details'" cols="2">
            @foreach ($campaignFields as $field)
            @php
                $visibilityRule = \Illuminate\Support\Js::from($field['visibility'] ?? null);
            @endphp
            <div @if(($field['field_width'] ?? '') === 'full') class="md:col-span-2" @endif
                 x-show="shouldShow('{{ $field['name'] }}', {{ $visibilityRule }})">
                @if(($field['type'] ?? 'text') === 'textarea')
                    <x-form.textarea :name="$field['name']" :label="$field['label']"
                        :value="$prefill[$field['name']] ?? ''"
                        :required="$field['required'] ?? false"
                        x-model="values['{{ $field['name'] }}']"
                        x-bind:disabled="!isVisible({{ $visibilityRule }})" />
                @elseif(($field['type'] ?? 'text') === 'select')
                    <div class="form-field">
                        <label class="form-label">
                            {{ $field['label'] }}
                            @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                        </label>
                        <select name="{{ $field['name'] }}" class="form-select" x-model="values['{{ $field['name'] }}']" x-bind:disabled="!isVisible({{ $visibilityRule }})" @if($field['required'] ?? false) required @endif>
                            <option value="">-- Select --</option>
                            @foreach(($field['options'] ?? []) as $opt)
                                @php
                                    $val     = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                                    $display = is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : $opt;
                                @endphp
                                <option value="{{ $val }}" @selected(($prefill[$field['name']] ?? '') == $val)>{{ $display }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif(($field['type'] ?? 'text') === 'multiselect')
                    @php
                        $multiSelected = [];
                        $multiRaw = $prefill[$field['name']] ?? '';
                        if (is_string($multiRaw) && $multiRaw !== '') {
                            $multiDec = json_decode($multiRaw, true);
                            $multiSelected = is_array($multiDec) ? $multiDec : [];
                        }
                    @endphp
                    <fieldset class="form-field min-w-0">
                        <legend class="form-label mb-2">
                            {{ $field['label'] }}
                            @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                        </legend>
                        <div class="flex flex-col gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-3">
                            @foreach(($field['options'] ?? []) as $opt)
                                @php
                                    $val     = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                                    $display = is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : $opt;
                                    $cbId = 'ms-' . $field['name'] . '-' . md5((string) $val);
                                @endphp
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-[var(--color-on-surface)]">
                                    <input type="checkbox" name="{{ $field['name'] }}[]" value="{{ $val }}" id="{{ $cbId }}"
                                        class="rounded border-[var(--color-border)]"
                                        x-bind:disabled="!isVisible({{ $visibilityRule }})"
                                        @checked(in_array((string) $val, array_map('strval', $multiSelected), true))>
                                    <span>{{ $display }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @elseif(($field['type'] ?? 'text') === 'percentage')
                    <div class="form-field">
                        <label class="form-label">
                            {{ $field['label'] }}
                            @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                        </label>
                        <div class="relative">
                            <input type="number"
                                   name="{{ $field['name'] }}"
                                   min="0"
                                   max="100"
                                   step="0.01"
                                   class="form-input pr-8"
                                   x-model="values['{{ $field['name'] }}']"
                                   x-bind:disabled="!isVisible({{ $visibilityRule }})"
                                   @if($field['required'] ?? false) required @endif>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-on-surface-dim)] text-sm">%</span>
                        </div>
                    </div>
                @else
                    <x-form.input :name="$field['name']" :label="$field['label']"
                        :type="$field['type'] === 'number' ? 'text' : ($field['type'] ?? 'text')"
                        :value="$prefill[$field['name']] ?? ''"
                        :required="$field['required'] ?? false"
                        x-model="values['{{ $field['name'] }}']"
                        x-bind:disabled="!isVisible({{ $visibilityRule }})" />
                @endif
            </div>
            @endforeach
        </x-form.group>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary" :disabled="submitting">
                <x-icon name="check" class="w-4 h-4" />
                <span x-text="submitting ? 'Saving...' : 'Save Record'">Save Record</span>
            </button>
            <a href="{{ route('dashboard') }}" class="btn-ghost">Cancel</a>
        </div>

        <div x-cloak
             x-show="reviewOpen"
             x-transition:enter="transition-opacity ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             x-trap.noscroll="reviewOpen"
             @keydown.escape.window="closeReview()"
             class="modal-backdrop"
             style="display: none;">
            <div class="modal-box max-w-2xl"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="crm-form-review-title"
                 @click.stop
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                <div class="modal-header">
                    <div>
                        <h2 id="crm-form-review-title" class="modal-title">Review your information</h2>
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Please check the details before saving this record.</p>
                    </div>
                    <button type="button" class="btn-icon" @click="closeReview()" aria-label="Close review">
                        <x-icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>
                <div class="modal-body">
                    <dl class="max-h-[55vh] overflow-y-auto divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)]">
                        <template x-for="field in reviewFields" :key="field.label">
                            <div class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] sm:gap-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]" x-text="field.label"></dt>
                                <dd class="whitespace-pre-wrap break-words text-sm text-[var(--color-on-surface)]" x-text="field.value"></dd>
                            </div>
                        </template>
                    </dl>
                    <p x-show="reviewFields.length === 0" class="py-4 text-center text-sm text-[var(--color-on-surface-dim)]">No form details to review.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" @click="closeReview()">Back to Form</button>
                    <button type="button" class="btn-primary" @click="confirmReview()" :disabled="submitting">
                        <x-icon name="check" class="w-4 h-4" />
                        <span x-text="submitting ? 'Saving...' : 'Confirm &amp; Save'">Confirm &amp; Save</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
