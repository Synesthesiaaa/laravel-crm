@props([
    'name',
    'label'       => null,
    'type'        => 'text',
    'value'       => null,
    'placeholder' => null,
    'required'    => false,
    'readonly'    => false,
    'disabled'    => false,
    'help'        => null,
    'error'       => null,
])
@php
    $hasError = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $errorMsg = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $inputId  = 'field-' . str_replace(['.', '[', ']'], ['-', '-', ''], $name);
@endphp
<div class="form-field">
    @if($label)
        <label for="{{ $inputId }}" class="form-label">
            {{ $label }}
            @if($required) <span class="text-[var(--color-danger)] ml-0.5">*</span> @endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $inputId }}"
        value="{{ old($name, $value) }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required)  required @endif
        @if($readonly)  readonly @endif
        @if($disabled)  disabled @endif
        {{ $attributes->class(['form-input', 'error' => $hasError]) }}
        @if($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
    >
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
