@props([
    'name',
    'label'       => null,
    'value'       => null,
    'rows'        => 3,
    'placeholder' => null,
    'required'    => false,
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
    <textarea
        name="{{ $name }}"
        id="{{ $inputId }}"
        rows="{{ $rows }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->class(['form-textarea', 'error' => $hasError]) }}
        @if($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
    >{{ old($name, $value) }}</textarea>
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
