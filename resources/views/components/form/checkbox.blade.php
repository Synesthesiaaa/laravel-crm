@props(['name', 'label', 'value' => '1', 'checked' => false, 'disabled' => false])
@php $inputId = 'field-' . str_replace(['.','[',']'],['-','-',''], $name); @endphp
<label class="flex items-center gap-2 cursor-pointer" for="{{ $inputId }}">
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $inputId }}"
        value="{{ $value }}"
        @checked(old($name, $checked))
        @if($disabled) disabled @endif
        {{ $attributes->class(['w-4 h-4 rounded border-[var(--color-border)] accent-[var(--color-primary)] cursor-pointer']) }}
    >
    <span class="text-sm text-[var(--color-on-surface)]">{{ $label }}</span>
</label>
