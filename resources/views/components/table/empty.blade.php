@props(['colspan' => 5, 'message' => 'No records found.', 'description' => null])
<tbody>
    <tr>
        <td colspan="{{ $colspan }}" class="table-empty">
            <x-icon name="document-text" class="w-10 h-10 mx-auto mb-2" />
            <p class="font-medium text-sm">{{ $message }}</p>
            @if($description)
                <p class="text-xs mt-1 text-[var(--color-on-surface-dim)]">{{ $description }}</p>
            @endif
            @if($slot->isNotEmpty())
                <div class="mt-4">{{ $slot }}</div>
            @endif
        </td>
    </tr>
</tbody>
