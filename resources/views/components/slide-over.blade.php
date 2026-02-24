@props(['name', 'title' => null])
<div x-show="$store.modal.is('{{ $name }}')"
     style="display: none;">
    <div class="slide-over-backdrop"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         @click="$store.modal.hide()">
    </div>
    <div class="slide-over-panel"
         x-trap.noscroll="$store.modal.is('{{ $name }}')"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
        <div class="slide-over-header">
            <h3 class="modal-title">{{ $title ?? 'Details' }}</h3>
            <button class="btn-icon" @click="$store.modal.hide()" aria-label="Close panel">
                <x-icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
        <div class="slide-over-body">{{ $slot }}</div>
    </div>
</div>
