@props(['columns' => [], 'sortBy' => null, 'sortDir' => 'asc', 'checkable' => false])
<thead>
    <tr>
        @if($checkable)
            <th class="w-10 px-3">
                <input type="checkbox" class="form-checkbox w-4 h-4 accent-[var(--color-primary)]" @click="toggleAll && toggleAll(allIds)">
            </th>
        @endif
        @foreach($columns as $col)
            @php
                $key       = is_array($col) ? ($col['key'] ?? null) : null;
                $label     = is_array($col) ? ($col['label'] ?? $col['key']) : $col;
                $sortable  = is_array($col) && ($col['sortable'] ?? false);
                $align     = is_array($col) ? ($col['align'] ?? 'left') : 'left';
                $active    = $key && $sortBy === $key;
                $nextDir   = ($active && $sortDir === 'asc') ? 'desc' : 'asc';
            @endphp
            <th @if($align !== 'left') style="text-align: {{ $align }}" @endif
                @if($sortable && $key) class="sortable" @endif>
                @if($sortable && $key)
                    <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir]) }}"
                       class="flex items-center gap-1 {{ $align === 'right' ? 'justify-end' : '' }} hover:text-[var(--color-on-surface)] transition-colors"
                       style="color: inherit; text-decoration: none;">
                        {{ $label }}
                        @if($active)
                            <x-icon :name="$sortDir === 'asc' ? 'chevron-down' : 'chevron-right'" class="w-3.5 h-3.5 rotate-{{ $sortDir === 'desc' ? '180' : '0' }}" />
                        @else
                            <span class="opacity-30"><x-icon name="chevron-down" class="w-3.5 h-3.5" /></span>
                        @endif
                    </a>
                @else
                    {{ $label }}
                @endif
            </th>
        @endforeach
        @if($slot->isNotEmpty())
            {{ $slot }}
        @endif
    </tr>
</thead>
