@props(['name', 'title' => null, 'maxWidth' => 'md'])
@php $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl']; $w = $widths[$maxWidth] ?? $widths['md']; @endphp
<div x-show="$store.modal.is('{{ $name }}')"
     x-trap.noscroll="$store.modal.is('{{ $name }}')"
     class="modal-backdrop"
     style="display: none;">
    <div class="modal-box {{ $w }}"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
        @if($title)
        <div class="modal-header">
            <h3 class="modal-title">{{ $title }}</h3>
            <button class="btn-icon" @click="$store.modal.hide()" aria-label="Close">
                <x-icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
        @endif
        <div class="modal-body">{{ $slot }}</div>
    </div>
</div>
