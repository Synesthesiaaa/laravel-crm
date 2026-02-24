@props(['align' => 'left', 'width' => '48'])
<div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
    <div @click="open = !open">
        {{ $trigger }}
    </div>
    <div x-show="open"
         @click.outside="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
         class="dropdown-panel {{ $align === 'right' ? 'right-0' : 'left-0' }}"
         style="display: none; min-width: {{ $width !== '48' ? $width : '12' }}rem">
        {{ $slot }}
    </div>
</div>
