@props([
    'name',
    'label'    => null,
    'options'  => [],
    'selected' => null,
    'required' => false,
    'disabled' => false,
    'empty'    => '-- Select --',
    'help'     => null,
    'error'    => null,
])
@php
    $hasError = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $errorMsg = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $inputId  = 'field-' . str_replace(['.', '[', ']'], ['-', '-', ''], $name);
    $current  = old($name, $selected);
@endphp
<div class="form-field">
    @if($label)
        <label for="{{ $inputId }}" class="form-label">
            {{ $label }}
            @if($required) <span class="text-[var(--color-danger)] ml-0.5">*</span> @endif
        </label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $inputId }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->class(['form-select', 'error' => $hasError]) }}
        @if($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
    >
        @if($empty !== false)
            <option value="">{{ $empty }}</option>
        @endif
        @foreach($options as $val => $display)
            <option value="{{ $val }}" @selected($current == $val)>{{ $display }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if($errorMsg)
        <span id="{{ $inputId }}-error" class="form-error-msg" role="alert">
            <x-icon name="exclamation-circle" class="w-3.5 h-3.5 shrink-0" />
            {{ $errorMsg }}
        </span>
    @endif
    @if($help && !$errorMsg)
        <span class="form-help">{{ $help }}</span>
    @endif
</div>
