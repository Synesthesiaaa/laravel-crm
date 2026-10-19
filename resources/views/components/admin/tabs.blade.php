@props([
    'tabs' => [],
    'active' => null,
    'label' => 'Sections',
])

<nav aria-label="{{ $label }}" class="workspace-tabs">
    <div class="workspace-tabs-scroll">
        @foreach($tabs as $tab)
            @php($isActive = ($tab['key'] ?? null) === $active)
            <a href="{{ $tab['href'] ?? '#' }}"
               class="workspace-tab {{ $isActive ? 'is-active' : '' }}"
               @if($isActive) aria-current="page" @endif>
                @if(!empty($tab['icon']))
                    <x-icon :name="$tab['icon']" class="h-4 w-4 shrink-0" />
                @endif
                {{ $tab['label'] ?? $tab['key'] ?? '' }}
            </a>
        @endforeach
    </div>
</nav>
