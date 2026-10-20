@props(['title', 'description' => null, 'breadcrumbs' => []])
<div>
    @if(count($breadcrumbs))
        <x-breadcrumbs :items="$breadcrumbs" />
    @endif
    <div class="page-header">
        <div class="min-w-0 flex-1">
            <h2 class="page-header-title">{{ $title }}</h2>
            @if($description)
                <p class="page-header-desc">{{ $description }}</p>
            @endif
        </div>
        @if($slot->isNotEmpty())
        <div class="page-header-actions">
            {{ $slot }}
        </div>
        @endif
    </div>
</div>
