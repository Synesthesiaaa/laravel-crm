@extends('layouts.app')

@section('title', $formName . ' - ' . $campaignName)
@section('header-icon')<x-icon name="document-text" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', $formName . ' Form')

@section('content')
<div class="max-w-3xl mx-auto">
    <x-breadcrumbs :items="[$formName => null]" />

    <form action="{{ route('forms.store') }}" method="POST"
          x-data="{ submitting: false }" @submit="submitting = true">
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

        {{-- System fields --}}
        <x-form.group title="Reference" cols="2">
            <div class="form-field">
                <span class="form-label">Request ID</span>
                <input type="hidden" name="request_id" value="">
                <p class="form-help text-[var(--color-on-surface-muted)] mt-1">A unique reference (ULID) is assigned when you save.</p>
            </div>
            <x-form.input name="date" type="date" label="Date" :value="$prefill['date'] ?? date('Y-m-d')" required />
        </x-form.group>

        {{-- VICIdial / lead fields --}}
        @if (!empty($viciFields))
        <x-form.group title="Lead Information (VICIdial)" cols="2">
            @foreach ($viciFields as $field)
                @if (!in_array($field['name'], ['request_id', 'date', 'agent']))
                <div @if(($field['field_width'] ?? '') === 'full') class="md:col-span-2" @endif>
                    @if(($field['type'] ?? 'text') === 'textarea')
                        <x-form.textarea :name="$field['name']" :label="$field['label']"
                            :value="$prefill[$field['name']] ?? ''"
                            :required="$field['required'] ?? false" />
                    @elseif(($field['type'] ?? 'text') === 'select')
                        <div class="form-field">
                            <label class="form-label">
                                {{ $field['label'] }}
                                @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                            </label>
                            <select name="{{ $field['name'] }}" class="form-select" @if($field['required'] ?? false) required @endif>
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
                                            @checked(in_array((string) $val, array_map('strval', $viciMultiSel), true))>
                                        <span>{{ $display }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <x-form.input
                            :name="$field['name']"
                            :label="$field['label']"
                            :type="$field['type'] === 'number' ? 'text' : ($field['type'] ?? 'text')"
                            :value="$prefill[$field['name']] ?? ''"
                            :required="$field['required'] ?? false" />
                    @endif
                </div>
                @endif
            @endforeach
        </x-form.group>
        @endif

        {{-- Campaign-specific fields --}}
        <x-form.group :title="$formName . ' Details'" cols="2">
            @foreach ($campaignFields as $field)
            <div @if(($field['field_width'] ?? '') === 'full') class="md:col-span-2" @endif>
                @if(($field['type'] ?? 'text') === 'textarea')
                    <x-form.textarea :name="$field['name']" :label="$field['label']"
                        :value="$prefill[$field['name']] ?? ''"
                        :required="$field['required'] ?? false" />
                @elseif(($field['type'] ?? 'text') === 'select')
                    <div class="form-field">
                        <label class="form-label">
                            {{ $field['label'] }}
                            @if($field['required'] ?? false)<span class="text-[var(--color-danger)] ml-0.5">*</span>@endif
                        </label>
                        <select name="{{ $field['name'] }}" class="form-select" @if($field['required'] ?? false) required @endif>
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
                                        @checked(in_array((string) $val, array_map('strval', $multiSelected), true))>
                                    <span>{{ $display }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @else
                    <x-form.input :name="$field['name']" :label="$field['label']"
                        :type="$field['type'] === 'number' ? 'text' : ($field['type'] ?? 'text')"
                        :value="$prefill[$field['name']] ?? ''"
                        :required="$field['required'] ?? false" />
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
    </form>
</div>
@endsection
