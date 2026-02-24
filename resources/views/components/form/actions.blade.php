@props(['cancelRoute' => null, 'submitLabel' => 'Save', 'cancelLabel' => 'Cancel'])
<div class="flex items-center gap-3 pt-2">
    <button type="submit" class="btn-primary">
        <x-icon name="check" class="w-4 h-4" />
        {{ $submitLabel }}
    </button>
    @if($cancelRoute)
        <a href="{{ $cancelRoute }}" class="btn-ghost">{{ $cancelLabel }}</a>
    @endif
    {{ $slot }}
</div>
