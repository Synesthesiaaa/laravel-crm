@props([
    'title',
    'items' => [],
    'tone' => 'default',
])

@php
    $headingId = 'admin-tool-grid-'.\Illuminate\Support\Str::slug($title);
    $dangerTone = $tone === 'danger';
@endphp

<section aria-labelledby="{{ $headingId }}">
    <h3 id="{{ $headingId }}" class="tool-directory-title">
        {{ $title }}
    </h3>

    <div class="tool-directory">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}" class="tool-directory-link">
                <x-icon :name="$item['icon']" class="w-4 h-4 shrink-0" />
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{ $item['label'] }}</span>
                    <span class="tool-directory-description">{{ $item['desc'] }}</span>
                </span>
                <x-icon name="chevron-right" class="w-4 h-4 shrink-0" />
            </a>
        @endforeach
    </div>
</section>
