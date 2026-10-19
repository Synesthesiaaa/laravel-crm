@props(['title' => null, 'description' => null, 'cols' => 2])
@php $colClass = match((int)$cols) { 1 => 'grid-cols-1', 3 => 'grid-cols-1 md:grid-cols-3', default => 'grid-cols-1 md:grid-cols-2' }; @endphp
<fieldset {{ $attributes->class('form-section mb-6') }}>
    @if($title)
        <legend class="form-section-title">{{ $title }}</legend>
    @endif
        @if($description)
            <p class="form-section-description">{{ $description }}</p>
        @endif
    <div class="grid {{ $colClass }} gap-4">
        {{ $slot }}
    </div>
</fieldset>
