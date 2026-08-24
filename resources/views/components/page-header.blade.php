@props(['title', 'description' => null, 'breadcrumbs' => []])
<div>
    @if(count($breadcrumbs))
        <x-breadcrumbs :items="$breadcrumbs" />
    @endif
    <div class="page-header">
        <div>
            <h2 class="page-header-title">{{ $title }}</h2>
            @if($description)
                <p class="page-header-desc">{{ $description }}</p>
            @endif
        </div>
        @if($slot->isNotEmpty())
        <div class="flex items-center gap-2 shrink-0">
            {{ $slot }}
        </div>
        @endif
    </div>
</div>
