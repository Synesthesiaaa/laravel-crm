@props(['name', 'title' => null, 'maxWidth' => 'md', 'pointerThroughBackdrop' => false])
@php $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl']; $w = $widths[$maxWidth] ?? $widths['md']; @endphp
<div x-show="$store.modal.is('{{ $name }}')"
     x-trap.noscroll="$store.modal.is('{{ $name }}')"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="modal-backdrop"
     role="presentation"
     style="display: none;{{ $pointerThroughBackdrop ? ' pointer-events: none;' : '' }}">
    <div class="modal-box {{ $w }}"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         {{ $attributes }}
         role="dialog"
         aria-modal="true"
         @if($title) aria-labelledby="modal-title-{{ $name }}" @endif
         style="{{ $pointerThroughBackdrop ? 'pointer-events: auto;' : '' }}"
         @click.stop>
        @if($title)
        <div class="modal-header">
            <h3 id="modal-title-{{ $name }}" class="modal-title">{{ $title }}</h3>
            <button type="button" class="btn-icon" @click="$store.modal.hide()" aria-label="Close dialog" title="Close dialog">
                <x-icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
        @endif
        <div class="modal-body">{{ $slot }}</div>
    </div>
</div>
